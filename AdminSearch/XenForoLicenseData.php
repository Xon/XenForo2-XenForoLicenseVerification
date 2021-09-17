<?php

namespace LiamW\XenForoLicenseVerification\AdminSearch;

use XF\AdminSearch\AbstractHandler;
use XF\Mvc\Entity\Entity;

class XenForoLicenseData extends AbstractHandler
{
	public function getDisplayOrder(): int
	{
		return 30;
	}

	public function search($text, $limit, array $previousMatchIds = []): \XF\Mvc\Entity\AbstractCollection
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

	public function getTemplateData(Entity $record): array
	{
		/** @var \LiamW\XenForoLicenseVerification\Entity\XenForoLicenseData $record */
		/** @var \XF\Mvc\Router $router */
		$router = $this->app->container('router.admin');

		if (!$record->valid)
		{
			$status = \XF::phrase('(liamw_xenforolicenseverification_xenforo_license_is_invalid)');
		}
		else if ($record->validation_date)
		{
			$status = \XF::phrase('(valid)');
		}
		else
		{
			$status = \XF::phrase('(liamw_xenforolicenseverification_xenforo_license_not_verified)');
		}

		return [
			'link'  => $router->buildLink('users/edit', $record->User),
			'title' => new \XF\PreEscaped(\XF::escapeString($record->domain) . ' ' . $status),
			'extra' => $record->User->username
		];
	}

	public function isSearchable(): bool
	{
		return \XF::visitor()->hasAdminPermission('user');
	}
}