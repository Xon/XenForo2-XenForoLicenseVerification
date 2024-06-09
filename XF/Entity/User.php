<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace LiamW\XenForoLicenseVerification\XF\Entity;

use LiamW\XenForoLicenseVerification\Entity\XenForoLicenseData;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\User
 *
 * @property XenForoLicenseData XenForoLicense
 */
class User extends XFCP_User
{
    /**
     * @param bool|null $removeCustomerToken
     * @param bool      $sendAlert
     */
    public function expireValidation(?bool $removeCustomerToken = null, bool $sendAlert = true): void
    {
        if ($removeCustomerToken === null)
        {
            $removeCustomerToken = !(\XF::options()->liamw_xenforolicenseverification_maintain_customer ?? true);
        }

        \XF::db()->beginTransaction();

        $this->XenForoLicense->deleteLicenseData($removeCustomerToken);

        if ($this->app()->options()->liamw_xenforolicenseverification_licensed_primary ?? false)
        {
            $this->user_group_id = $this::GROUP_REG;
            $this->saveIfChanged($saved, true, false);
        }

        /** @var \XF\Service\User\UserGroupChange $userGroupChangeService */
        $userGroupChangeService = \XF::app()->service('XF:User\UserGroupChange');
        $userGroupChangeService->removeUserGroupChange($this->user_id, 'xfLicenseValid');
        $userGroupChangeService->removeUserGroupChange($this->user_id, 'xfLicenseTransferable');

        \XF::db()->commit();

        if ($sendAlert)
        {
            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = \XF::repository('XF:UserAlert');
            $alertRepo->alert($this, $this->user_id, $this->username, 'user', $this->user_id, 'xflicenseverification_lapsed');
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['XenForoLicense'] = [
            'entity'        => 'LiamW\XenForoLicenseVerification:XenForoLicenseData',
            'type'          => Entity::TO_ONE,
            'conditions'    => 'user_id',
            'primary'       => true,
            'cascadeDelete' => true,
            'api'           => true
        ];

        return $structure;
    }
}