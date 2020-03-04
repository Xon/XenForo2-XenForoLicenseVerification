<?php

namespace LiamW\XenForoLicenseVerification\AdminSearch;

use XF\AdminSearch\AbstractHandler;
use XF\Mvc\Entity\Entity;

class XenForoLicenseData extends AbstractHandler
{
	public function getDisplayOrder()
	{
		return 30;
	}

	public function search($text, $limit, array $previousMatchIds = [])
	{
		$finder = $this->app->finder('LiamW\XenForoLicenseVerification:XenForoLicenseData');

		$conditions = [
			['validation_token', 'like', $finder->escapeLike($text, '%?%')],
			['customer_token', 'like', $finder->escapeLike($text, '%?%')],
			['license_token', 'like', $finder->escapeLike($text, '%?%')],
			['domain', 'like', $finder->escapeLike($text, '%?%')],
		];
		if ($previousMatchIds)
		{
			$conditions[] = ['user_id', $previousMatchIds];
		}

		$finder
			->with('User')
			->whereOr($conditions)
			->order('domain')
			->limit($limit);

		return $finder->fetch();
	}

	public function getTemplateData(Entity $record)
	{
		/** @var \LiamW\XenForoLicenseVerification\Entity\XenForoLicenseData $record */
		/** @var \XF\Mvc\Router $router */
		$router = $this->app->container('router.admin');

		return [
			'link'  => $router->buildLink('users/edit', $record->User),
			'title' => $record->domain,
			'extra' => $record->User->username
		];
	}

	public function isSearchable()
	{
		return \XF::visitor()->hasAdminPermission('user');
	}
}