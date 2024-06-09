<?php

namespace LiamW\XenForoLicenseVerification\Finder;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\AbstractCollection as AbstractCollection;
use LiamW\XenForoLicenseVerification\Entity\XenForoLicenseData as XenForoLicenseDataEntity;

/**
 * @method AbstractCollection<XenForoLicenseDataEntity>|XenForoLicenseDataEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method XenForoLicenseDataEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,XenForoLicenseDataEntity>
 * @extends Finder<XenForoLicenseDataEntity>
 */
class XenForoLicenseData extends Finder
{
}
