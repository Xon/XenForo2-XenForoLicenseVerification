<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace LiamW\XenForoLicenseVerification\XF\Service\User;

use LiamW\XenForoLicenseVerification\Service\XenForoLicense\Verifier as VerifierService;
use SV\StandardLib\Helper;

class Registration extends XFCP_Registration
{
    // Ugly field name, but it'll prevent clashes.
    protected $liamw_xenforolicenseverification_xenforo_license_data;

    /** @var VerifierService */
    protected $liamw_xenforolicenseverification_verificationService = null;

    public function setFromInput(array $input)
    {
        parent::setFromInput($input);

        $this->liamw_xenforolicenseverification_xenforo_license_data = $input['xenforo_license_verification'];
    }

    protected function applyExtraValidation()
    {
        parent::applyExtraValidation();

        $this->verifyXenForoLicense($this->liamw_xenforolicenseverification_xenforo_license_data);
    }

    protected function verifyXenForoLicense(array $verificationData): void
    {
        if ($this->app->options()->liamw_xenforolicenseverification_registration['request'])
        {
            if ($this->app->options()->liamw_xenforolicenseverification_registration['require'] && !$verificationData['token'])
            {
                $this->user->error(\XF::phrase('liamw_xenforolicenseverification_please_enter_a_valid_xenforo_license_validation_token'));

                return;
            }
            else if (!$verificationData['token'])
            {
                return;
            }

            $this->liamw_xenforolicenseverification_verificationService = Helper::service(VerifierService::class, null, $verificationData['token'], $verificationData['domain']);

            if (!$this->liamw_xenforolicenseverification_verificationService->isValid($error))
            {
                $this->user->error($error);
            }
        }
    }

    protected function _save()
    {
        $user = parent::_save();

        if ($this->liamw_xenforolicenseverification_verificationService)
        {
            $this->liamw_xenforolicenseverification_verificationService->applyLicenseData($user);
        }

        return $user;
    }
}