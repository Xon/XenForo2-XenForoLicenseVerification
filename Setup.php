<?php

namespace LiamW\XenForoLicenseVerification;

use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\CronEntry;

class Setup extends AbstractSetup
{
	use InstallerHelper;
	use StepRunnerInstallTrait;
	use StepRunnerUninstallTrait;
	use StepRunnerUpgradeTrait;

	public function installStep1()
	{
		$sm = $this->schemaManager();

		foreach ($this->getTables() as $tableName => $callback)
		{
			$sm->createTable($tableName, $callback);
			$sm->alterTable($tableName, $callback);
		}
	}

	public function upgrade10202Step1(): void
	{
		$this->schemaManager()->alterTable('xf_user', function (Alter $table) {
			$table->addColumn('api_customer_token', 'varchar', 32)->nullable();
			$table->addColumn('api_license_token', 'varchar', 32)->nullable();
			$table->addColumn('api_license_valid', 'bool')->nullable();
		});
	}

	public function upgrade20000Step1(): void
	{
		$this->installStep1();
	}

	public function upgrade20000Step2(): void
	{
		if (!$this->columnExists('xf_liamw_xenforo_license_data', 'api_key'))
		{
			return;
		}

		/** @noinspection SqlResolve */
		$this->db()->query("
			INSERT INTO xf_liamw_xenforo_license_data(user_id, validation_token, customer_token, license_token, domain, domain_match, can_transfer, validation_date) 
			SELECT user_id, api_key, api_customer_token, api_license_token, api_domain, NULL, 0, IF(xf_user.api_expiry - ? < 0, 0, xf_user.api_expiry - ?)
			FROM xf_user 
			WHERE xenforo_api_validation_token IS NOT NULL
		");
	}

	public function upgrade20000Step3(): void
	{
		$this->schemaManager()->alterTable('xf_user', function (Alter $table) {
			$table->dropColumns([
				'api_key',
				'api_domain',
				'api_expiry',
				'api_customer_token',
				'api_license_token',
				'api_license_valid'
			]);
		});
	}

	public function upgrade3000031Step1(): void
	{
		$this->installStep1();
	}

	public function upgrade3000031Step2(): void
	{
		if (!$this->columnExists('xf_user_profile', 'xenforo_api_validation_token'))
		{
			return;
		}

		/** @noinspection SqlResolve */
		$this->db()->query("
			INSERT INTO xf_liamw_xenforo_license_data(user_id, validation_token, customer_token, license_token, domain, domain_match, can_transfer, validation_date) 
			SELECT user_id, xenforo_api_validation_token, xenforo_api_customer_token, xenforo_api_license_token, xenforo_api_validation_domain, NULL, 0, xenforo_api_last_check 
			FROM xf_user_profile 
			WHERE xenforo_api_validation_token IS NOT NULL
		");
	}

	public function upgrade3000031Step3(): void
	{
		$this->schemaManager()->alterTable('xf_user_profile', function (Alter $table) {
			$table->dropColumns([
				'xenforo_api_validation_token',
				'xenforo_api_validation_domain',
				'xenforo_api_last_check',
				'xenforo_api_customer_token',
				'xenforo_api_license_token',
				'xenforo_api_license_valid'
			]);
		});
	}

	public function upgrade3040002Step1(): void
	{
		$this->installStep1();
	}

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		foreach ($this->getTables() as $tableName => $callback)
		{
			$sm->dropTable($tableName);
		}
	}

	public function postInstall(array &$stateChanges): void
	{
		$this->randomiseCronTime();
	}

	public function postUpgrade($previousVersion, array &$stateChanges): void
	{
		$this->randomiseCronTime();
	}

	public function onActiveChange($newActive, array &$jobList): void
	{
		// Only randomise when enabled
		if ($newActive)
		{
			$this->randomiseCronTime();
		}
	}

	protected function randomiseCronTime(): void
	{
		if (\XF::app()->config('development')['enabled'])
		{
			return;
		}

		/** @var CronEntry $recheckCron */
		$recheckCron = \XF::app()->find('XF:CronEntry', 'liamw_xenforolicenseexpir');

		if ($recheckCron)
		{
			$runRules = $recheckCron->run_rules;
			$runRules['hours'] = [mt_rand(0, 23)];
			$runRules['minutes'] = [mt_rand(0, 59)];

			$recheckCron->run_rules = $runRules;
			$recheckCron->save();
		}
	}

	protected function getTables(): array
	{
		return [
			'xf_liamw_xenforo_license_data' => function ($table) {
				/** @var Create|Alter $table */
				$this->addOrChangeColumn($table,'user_id', 'int')->primaryKey();
				$this->addOrChangeColumn($table,'validation_token', 'varchar', 50)->nullable();
				$this->addOrChangeColumn($table,'customer_token', 'varchar', 50);
				$this->addOrChangeColumn($table,'license_token', 'varchar', 50)->nullable();
				$this->addOrChangeColumn($table,'domain', 'varchar', 255)->nullable();
				$this->addOrChangeColumn($table,'domain_match', 'bool')->nullable();
				$this->addOrChangeColumn($table,'can_transfer', 'bool')->nullable();
				$this->addOrChangeColumn($table,'validation_date', 'int')->nullable();
				$this->addOrChangeColumn($table,'valid', 'tinyint')->setDefault(1);
			},
		];
	}
}