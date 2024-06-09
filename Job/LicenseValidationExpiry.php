<?php

namespace LiamW\XenForoLicenseVerification\Job;

use LiamW\XenForoLicenseVerification\Service\XenForoLicense\Verifier as VerifierService;
use SV\StandardLib\Helper;
use XF\Job\AbstractJob;

class LicenseValidationExpiry extends AbstractJob
{
    const MAX_BATCH_SIZE = 5;

    protected $defaultData = [
        'start' => 0,
        'batch' => self::MAX_BATCH_SIZE,
    ];

    public function run($maxRunTime): \XF\Job\JobResult
    {
        $startTime = microtime(true);

        $validationCutoff = \XF::$time - (\XF::app()
                                             ->options()->liamw_xenforolicenseverification_cutoff * 24 * 60 * 60);

        $this->data['batch'] = \min(\max($this->data['batch'], 1), self::MAX_BATCH_SIZE);
        $expiredUsers = \XF::app()->finder('XF:User')
                           ->where('XenForoLicense.user_id', '>', $this->data['start'])
                           ->where('XenForoLicense.validation_date', '<=', $validationCutoff)
                           ->where('XenForoLicense.valid', '=', 1)
                           ->order('XenForoLicense.user_id')
                           ->fetch($this->data['batch']);

        if (!$expiredUsers->count())
        {
            return $this->complete();
        }

        $recheck = \XF::app()->options()->liamw_xenforolicenseverification_auto_recheck ?? false;

        $done = 0;

        /** @var \LiamW\XenForoLicenseVerification\XF\Entity\User $expiredUser */
        foreach ($expiredUsers as $expiredUser)
        {
            $done++;

            $validationService = Helper::service(VerifierService::class, $expiredUser, $expiredUser->XenForoLicense->validation_token, $expiredUser->XenForoLicense->domain);

            if ($recheck && $expiredUser->XenForoLicense->validation_token)
            {
                if ($validationService->isValid())
                {
                    $validationService->applyLicenseData($expiredUser);
                }
                else if ($validationService->isApiFailure())
                {
                    // try again in 30 minutes
                    $resume = $this->resume();
                    $resume->continueDate = \XF::$time + 30 * 60;

                    return $resume;
                }
                else
                {
                    $expiredUser->expireValidation();
                }
            }
            else
            {
                $expiredUser->expireValidation();
            }

            $this->data['start'] = $expiredUser->user_id;

            if (microtime(true) - $startTime >= $maxRunTime)
            {
                break;
            }
        }

        $this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $startTime, $maxRunTime, self::MAX_BATCH_SIZE);
        $resume = $this->resume();
        $resume->continueDate = \XF::$time + 5;

        return $resume;
    }

    public function getStatusMessage(): string
    {
        $actionPhrase = \XF::phrase('liamw_xenforolicenseverification_updating_xenforo_license_verifications');

        return sprintf('%s... (%s)', $actionPhrase, $this->data['start']);
    }

    public function canCancel(): bool
    {
        return false;
    }

    public function canTriggerByChoice(): bool
    {
        return false;
    }
}