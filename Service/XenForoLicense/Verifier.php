<?php

namespace LiamW\XenForoLicenseVerification\Service\XenForoLicense;

use LiamW\XenForoLicenseVerification\Entity\XenForoLicenseData;
use LiamW\XenForoLicenseVerification\XFApi;
use SV\StandardLib\Helper;
use XF\Entity\User;
use XF\Service\AbstractService;

class Verifier extends AbstractService
{
    /** @var XFApi */
    protected $api;
    /** @var string */
    protected $token;
    /** @var ?string */
    protected $domain;

    protected $options = [
        'uniqueChecks'          => [
            'customer' => null,
            'license'  => null
        ],
        'licensedUserGroup'     => [
            'id'           => null,
            'setAsPrimary' => null
        ],
        'transferableUserGroup' => null,
        'checkDomain'           => null
    ];

    protected $apiFailure = false;
    protected $errors     = [];
    /** @var ?bool */
    protected $isValid = null;
    /** @var ?User */
    protected $user;

    public function __construct(\XF\App $app, ?User $user, string $token, string $domain = null, array $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->user = $user;
        $this->token = $token;
        $this->domain = $domain ?? '';

        parent::__construct($app);
    }

    public function isApiFailure(): bool
    {
        return $this->apiFailure;
    }

    public function setApiFailure(bool $apiFailure)
    {
        $this->apiFailure = $apiFailure;
    }

    protected function setup()
    {
        $this->processOptionDefaults();

        // trim out whitespace
        $this->token = \preg_replace('/[\s\r\n]/', '', $this->token);

        // token for XF licences vs Cloud licenses are slightly different
        if (!\preg_match('/^(?:cl_|)[a-f0-9]{32}$/i', $this->token))
        {
            $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_token');
        }

        if ($this->options['checkDomain'] && \strlen($this->domain) === 0)
        {
            $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_domain');
        }

        $this->api = Helper::newExtendedClass(XFApi::class, $this->app->http()->client(), $this->token, $this->domain);
    }

    protected function processOptionDefaults()
    {
        if ($this->options['uniqueChecks']['customer'] === null)
        {
            $this->options['uniqueChecks']['customer'] = $this->app->options()->liamw_xenforolicenseverification_unique_customer ?? false;
        }

        if ($this->options['uniqueChecks']['license'] === null)
        {
            $this->options['uniqueChecks']['license'] = $this->app->options()->liamw_xenforolicenseverification_unique_license ?? false;
        }

        if ($this->options['checkDomain'] === null)
        {
            $this->options['checkDomain'] = $this->app->options()->liamw_xenforolicenseverification_check_domain ?? true;
        }

        if ($this->options['licensedUserGroup']['id'] === null)
        {
            $this->options['licensedUserGroup']['id'] = $this->app->options()->liamw_xenforolicenseverification_licensed_group ?? 0;
        }

        if ($this->options['licensedUserGroup']['setAsPrimary'] === null)
        {
            $this->options['licensedUserGroup']['setAsPrimary'] = (bool)($this->app->options()->liamw_xenforolicenseverification_licensed_primary ?? 0);
        }

        if ($this->options['transferableUserGroup'] === null)
        {
            $this->options['transferableUserGroup'] = $this->app->options()->liamw_xenforolicenseverification_transfer_group ?? 0;
        }
    }

    public function isValid(&$error = ''): bool
    {
        if ($this->errors)
        {
            $error = reset($this->errors);

            return false;
        }

        if ($this->isValid !== null)
        {
            return $this->isValid;
        }

        $this->api->validate();

        $responseCode = $this->api->getResponseCode();
        if ($responseCode >= 500)
        {
            $this->setApiFailure(true);
            $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_error_occurred_while_attempting_to_verify_your_xenforo_license');

            return false;
        }

        if ($responseCode !== 200)
        {
            $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_token');
        }
        else if (!$this->api->is_valid)
        {
            $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_token');
        }

        if ($this->options['checkDomain'] && !$this->api->domain_match)
        {
            $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_domain_not_match_license');
        }

        $userId = $this->user->user_id ?? 0;
        if ($this->options['uniqueChecks']['license'])
        {
            $finder = $this->finder('XF:User')
                           ->where('user_id', '!=', $userId);
            if ($this->api->license_token)
            {
                $finder->where('XenForoLicense.license_token', $this->api->license_token);
            }
            else if ($this->api->subscription_token)
            {
                $finder->where('XenForoLicense.subscription_token', $this->api->subscription_token);
            }

            if ($finder->total() > 0)
            {
                $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_license_token_not_unique');
            }
        }

        if ($this->options['uniqueChecks']['customer'])
        {
            if ($this->finder('XF:User')
                     ->where('user_id', '!=', $userId)
                     ->where('XenForoLicense.customer_token', $this->api->customer_token)
                     ->total() > 0)
            {
                $this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_customer_token_not_unique');
            }
        }

        $error = reset($this->errors);
        $this->isValid = empty($this->errors);

        return $this->isValid;
    }

    public function applyLicenseData(User $user)
    {
        \XF::db()->beginTransaction();

        /** @var XenForoLicenseData $licenseData */
        $licenseData = $user->getRelationOrDefault('XenForoLicense');
        $licenseData->valid = true;
        $licenseData->validation_token = $this->api->validation_token;
        $licenseData->customer_token = $this->api->customer_token;
        $licenseData->license_token = $this->api->license_token;
        $licenseData->subscription_token = $this->api->subscription_token;
        $licenseData->can_transfer = $this->api->can_transfer;
        $licenseData->is_cloud = $this->api->is_cloud;
        $licenseData->domain = $this->api->test_domain;
        $licenseData->domain_match = $this->api->domain_match;
        $licenseData->validation_date = \XF::$time;

        if ($this->options['licensedUserGroup']['setAsPrimary'] === true && $this->options['licensedUserGroup']['id'])
        {
            $user->user_group_id = $this->options['licensedUserGroup']['id'];
        }

        if ($this->options['licensedUserGroup']['setAsPrimary'] !== true && $this->options['licensedUserGroup']['id'])
        {
            $userGroupChangeService = Helper::service(\XF\Service\User\UserGroupChange::class);
            $userGroupChangeService->addUserGroupChange($user->user_id, 'xfLicenseValid', $this->options['licensedUserGroup']['id']);
        }

        if ($this->options['transferableUserGroup'] && $this->api->can_transfer)
        {
            $userGroupChangeService = Helper::service(\XF\Service\User\UserGroupChange::class);
            $userGroupChangeService->addUserGroupChange($user->user_id, 'xfLicenseTransferable', $this->options['transferableUserGroup']);
        }

        $user->save(true, false);
        \XF::db()->commit();
    }
}