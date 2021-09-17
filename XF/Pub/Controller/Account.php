<?php

namespace LiamW\XenForoLicenseVerification\XF\Pub\Controller;

use XF\Mvc\Reply\AbstractReply;

class Account extends XFCP_Account
{
	public function actionXenForoLicense(): AbstractReply
	{
		if (\XF::visitor()->user_state !== 'valid')
		{
			return $this->noPermission();
		}

		$view = $this->view('LiamW\XenForoLicenseVerification:Account\XenForoLicense', 'liamw_xenforolicenseverification_verify_license');

		return $this->addAccountWrapperParams($view, 'liamw_xenforo_license');
	}

	public function actionXenForoLicenseUpdate(): AbstractReply
	{
		if (\XF::visitor()->user_state !== 'valid')
		{
			return $this->redirect($this->buildLink('account/xenforo-license'));
		}

		if ($this->isPost())
		{
			$input = $this->filter([
				'xenforo_license_verification' => [
					'token'  => 'str',
					'domain' => 'str'
				]
			]);

			/** @var \LiamW\XenForoLicenseVerification\Service\XenForoLicense\Verifier $verificationService */
			$verificationService = $this->service('LiamW\XenForoLicenseVerification:XenForoLicense\Verifier', $input['xenforo_license_verification']['token'], $input['xenforo_license_verification']['domain']);

			if ($verificationService->isValid($error))
			{
				$user = \XF::visitor();
				$verificationService->applyLicenseData($user);

				return $this->redirect($this->buildLink('account/xenforo-license'));
			}

			return $this->error($error);
		}

		$view = $this->view('LiamW\XenForoLicenseVerification:Account\XenForoLicense', 'liamw_xenforolicenseverification_update_license');

		return $this->addAccountWrapperParams($view, 'liamw_xenforo_license');
	}

	public function actionXenForoLicenseRemove(): AbstractReply
	{
		/** @var \LiamW\XenForoLicenseVerification\XF\Entity\User $user */
		$user = \XF::visitor();
		if ($user->user_state != 'valid' || !$user->XenForoLicense)
		{
			return $this->redirect($this->buildLink('account/xenforo-license'));
		}

		if ($this->isPost())
		{
			$user->expireValidation(false, false);

			return $this->redirect($this->buildLink('account/xenforo-license'));
		}

		return $this->view('LiamW\XenForoLicenseVerification:Account\XenForoLicense\Remove', 'liamw_xenforolicenseverification_remove_license');
	}
}