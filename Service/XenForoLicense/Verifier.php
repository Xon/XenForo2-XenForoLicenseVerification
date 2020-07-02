<?php

namespace LiamW\XenForoLicenseVerification\Service\XenForoLicense;

use LiamW\XenForoLicenseVerification\XFApi;
use XF\Entity\User;
use XF\Service\AbstractService;

class Verifier extends AbstractService
{
	/** @var XFApi */
	protected $api;
	protected $token;
	protected $domain;

	protected $options = [
		'uniqueChecks' => [
			'customer' => null,
			'license' => null
		],
		'licensedUserGroup' => [
			'id' => null,
			'setAsPrimary' => null
		],
		'transferableUserGroup' => null,
		'checkDomain' => null
	];

	protected $errors = [];
	protected $isValid = null;

	public function __construct(\XF\App $app, $token, $domain = null, array $options = [])
	{
		$this->options = array_merge($this->options, $options);
		$this->token = $token;
		$this->domain = $domain;

		parent::__construct($app);
	}

	protected function setup()
	{
		$this->processOptionDefaults();

		$this->api = new XFApi($this->app->http()->client(), $this->token, $this->domain);

		if (!$this->token || strlen($this->token) != 32 || !preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $this->token))
		{
			$this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_token');
		}

		if ($this->options['checkDomain'] && !$this->domain)
		{
			$this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_domain');
		}
	}

	protected function processOptionDefaults()
	{
		if ($this->options['uniqueChecks']['customer'] === null)
		{
			$this->options['uniqueChecks']['customer'] = $this->app->options()->liamw_xenforolicenseverification_unique_customer;
		}

		if ($this->options['uniqueChecks']['license'] === null)
		{
			$this->options['uniqueChecks']['license'] = $this->app->options()->liamw_xenforolicenseverification_unique_license;
		}

		if ($this->options['checkDomain'] === null)
		{
			$this->options['checkDomain'] = $this->app->options()->liamw_xenforolicenseverification_check_domain;
		}

		if ($this->options['licensedUserGroup']['id'] === null)
		{
			$this->options['licensedUserGroup']['id'] = $this->app->options()->liamw_xenforolicenseverification_licensed_group;
		}

		if ($this->options['licensedUserGroup']['setAsPrimary'] === null)
		{
			$this->options['licensedUserGroup']['setAsPrimary'] = (bool)$this->app->options()->liamw_xenforolicenseverification_licensed_primary;
		}

		if ($this->options['transferableUserGroup'] === null)
		{
			$this->options['transferableUserGroup'] = $this->app->options()->liamw_xenforolicenseverification_transfer_group;
		}
	}

	public function isValid(&$error = '')
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

		if ($this->api->getResponseCode() == 503 && \XF::options()->liamw_xenforolicenseverification_rate_limit_action == 'block')
		{
			$this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_error_occurred_while_attempting_to_verify_your_xenforo_license');
		}

		if (!$this->api->licenseExists())
		{
			$this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_token');
		}

		if (!$this->api->is_valid)
		{
			$this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_token');
		}

		if ($this->options['checkDomain'] && !$this->api->domain_match)
		{
			$this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_domain_not_match_license');
		}

		if ($this->options['uniqueChecks']['license'])
		{
			if ($this->finder('XF:User')->where('user_id', '!=', \XF::visitor()->user_id)
					->where('XenForoLicense.license_token', $this->api->license_token)->total() > 0)
			{
				$this->errors[] = \XF::phraseDeferred('liamw_xenforolicenseverification_license_token_not_unique');
			}
		}

		if ($this->options['uniqueChecks']['customer'])
		{
			if ($this->finder('XF:User')->where('user_id', '!=', \XF::visitor()->user_id)
					->where('XenForoLicense.customer_token', $this->api->customer_token)->total() > 0)
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

		$licenseData = $user->getRelationOrDefault('XenForoLicense');
		$licenseData->bulkSet([
			'validation_token' => $this->api->validation_token,
			'customer_token' => $this->api->customer_token,
			'license_token' => $this->api->license_token,
			'can_transfer' => $this->api->can_transfer,
			'domain' => $this->api->test_domain,
			'domain_match' => $this->api->domain_match,
			'validation_date' => \XF::$time
		]);

		if ($this->options['licensedUserGroup']['setAsPrimary'] === true && $this->options['licensedUserGroup']['id'])
		{
			$user->user_group_id = $this->options['licensedUserGroup']['id'];
		}

		if ($this->options['licensedUserGroup']['setAsPrimary'] !== true && $this->options['licensedUserGroup']['id'])
		{
			/** @var \XF\Service\User\UserGroupChange $userGroupChangeService */
			$userGroupChangeService = \XF::app()->service('XF:User\UserGroupChange');
			$userGroupChangeService->addUserGroupChange($user->user_id, 'xfLicenseValid', $this->options['licensedUserGroup']['id']);
		}

		if ($this->options['transferableUserGroup'] && $this->api->can_transfer)
		{
			/** @var \XF\Service\User\UserGroupChange $userGroupChangeService */
			$userGroupChangeService = \XF::app()->service('XF:User\UserGroupChange');
			$userGroupChangeService->addUserGroupChange($user->user_id, 'xfLicenseTransferable', $this->options['transferableUserGroup']);
		}

		$user->save(true, false);
		\XF::db()->commit();
	}
}