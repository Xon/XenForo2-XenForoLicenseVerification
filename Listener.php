<?php

namespace LiamW\XenForoLicenseVerification;

class Listener
{
	/**
	 * @param \XF\Service\User\ContentChange $changeService
	 * @param array                          $updates
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function userChange(\XF\Service\User\ContentChange $changeService, array &$updates): void
	{
		$updates['xf_liamw_xenforo_license_data'] = ['user_id', 'emptyable' => false];
	}
}