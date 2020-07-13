<?php

namespace LiamW\XenForoLicenseVerification\Job;

use XF\Job\AbstractJob;

class LicenseValidationExpiry extends AbstractJob
{
	protected $defaultData = [
		'start' => 0,
		'batch' => 50
	];

	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$validationCutoff = \XF::$time - (\XF::app()
					->options()->liamw_xenforolicenseverification_cutoff * 24 * 60 * 60);

		$expiredUsers = \XF::app()->finder('XF:User')->where('user_id', '>', $this->data['start'])
			->where('XenForoLicense.validation_date', '<=', $validationCutoff)
			->order('user_id')
			->fetch(min(max($this->data['batch'], 1), 50));

		if (!$expiredUsers->count())
		{
			return $this->complete();
		}

		$recheck = \XF::app()->options()->liamw_xenforolicenseverification_auto_recheck;

		$done = 0;

		/** @var \LiamW\XenForoLicenseVerification\XF\Entity\User $expiredUser */
		foreach ($expiredUsers AS $expiredUser)
		{
			$done++;

			/** @var \LiamW\XenForoLicenseVerification\Service\XenForoLicense\Verifier $validationService */
			$validationService = \XF::service('LiamW\XenForoLicenseVerification:XenForoLicense\Verifier', $expiredUser->XenForoLicense->validation_token, $expiredUser->XenForoLicense->domain);

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

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $startTime, $maxRunTime, 50);
		$resume = $this->resume();
		$resume->continueDate = \XF::$time + 10;

		return $resume;
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('liamw_xenforolicenseverification_updating_xenforo_license_verifications');

		return sprintf('%s... (%s)', $actionPhrase, $this->data['start']);
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}