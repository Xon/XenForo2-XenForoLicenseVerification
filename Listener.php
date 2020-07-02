<?php

namespace LiamW\XenForoLicenseVerification;

use XF\Mvc\Entity\Entity;

class Listener
{
	/**
	 * @param \XF\Service\User\ContentChange $changeService
	 * @param array                          $updates
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function userChange(\XF\Service\User\ContentChange $changeService, array &$updates)
	{
		$updates['xf_liamw_xenforo_license_data'] = ['user_id', 'emptyable' => false];
	}
}