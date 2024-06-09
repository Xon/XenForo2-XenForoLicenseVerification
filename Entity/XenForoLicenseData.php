<?php

namespace LiamW\XenForoLicenseVerification\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int            $user_id
 * @property string|null    $validation_token
 * @property string         $customer_token
 * @property string|null    $license_token
 * @property string|null    $subscription_token
 * @property string|null    $domain
 * @property bool|null      $domain_match
 * @property bool|null      $can_transfer
 * @property bool|null      $is_cloud
 * @property int|null       $validation_date
 * @property bool           $valid
 *
 * RELATIONS
 * @property-read User|null $User
 */
class XenForoLicenseData extends Entity
{
    public function deleteLicenseData(bool $removeCustomerToken = false): void
    {
        if ($removeCustomerToken)
        {
            $this->delete();
        }
        else
        {
            $this->valid = false;
            $this->save();
        }
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->shortName = 'LiamW\XenForoLicenseVerification:XenForoLicenseData';

        $structure->table = 'xf_liamw_xenforo_license_data';
        $structure->primaryKey = 'user_id';

        $structure->columns = [
            'user_id'          => [
                'type'     => self::UINT,
                'required' => true,
                'api'      => true
            ],
            'validation_token' => [
                'type'      => self::STR,
                'maxLength' => 50,
                'required'  => true,
                'nullable'  => true,
                'api'       => true
            ],
            'customer_token'   => [
                'type'      => self::STR,
                'maxLength' => 50,
                'required'  => true,
                'api'       => true
            ],
            'license_token'    => [
                'type'      => self::STR,
                'maxLength' => 50,
                'required'  => true,
                'nullable'  => true,
                'api'       => true
            ],
            'subscription_token'    => [
                'type'      => self::STR,
                'maxLength' => 50,
                'required'  => true,
                'nullable'  => true,
                'api'       => true
            ],
            'domain'           => [
                'type'      => self::STR,
                'maxLength' => 255,
                'required'  => true,
                'nullable'  => true,
                'api'       => true
            ],
            'domain_match'     => [
                'type'      => self::BOOL,
                'required'  => true,
                'nullable'  => true,
                'api'       => true,
                'changeLog' => false
            ],
            'can_transfer'     => [
                'type'     => self::BOOL,
                'required' => true,
                'nullable' => true,
                'api'      => true
            ],
            'is_cloud'     => [
                'type'      => self::BOOL,
                'required'  => true,
                'nullable'  => true,
                'api'       => true,
                'changeLog' => false
            ],
            'validation_date'  => [
                'type'     => self::UINT,
                'default'  => \XF::$time,
                'nullable' => true,
                'api'      => true
            ],
            'valid'            => [
                'type'     => self::BOOL,
                'required' => false,
                'default'  => true,
                'api'      => true
            ],
        ];
        $structure->relations = [
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
        ];
        $structure->behaviors = [
            'XF:ChangeLoggable' => [
                'contentType'     => 'user',
                'contentIdColumn' => 'user_id'
            ]
        ];

        return $structure;
    }

    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param int                         $verbosity
     * @param array                       $options
     * @api-type XenForoLicense
     * @api-desc Information about a user's XenForo license.
     * @api-out  str $validation_token
     * @api-out  str $customer_token
     * @api-out  str $license_token
     * @api-out  str $domain
     * @api-out  bool $domain_match
     * @api-out  bool $can_transfer
     * @api-out  uint $validation_date
     */
    protected function setupApiResultData(\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = [])
    {
    }
}