<?php

namespace LiamW\XenForoLicenseVerification\Cron;

class LicenseValidationExpiry
{
    public static function run(): void
    {
        \XF::app()
           ->jobManager()
           ->enqueueUnique('liamw_xenforolicenseverification_renew', 'LiamW\XenForoLicenseVerification:LicenseValidationExpiry', [], false);
    }
}