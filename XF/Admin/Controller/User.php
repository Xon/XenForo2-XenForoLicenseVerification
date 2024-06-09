<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace LiamW\XenForoLicenseVerification\XF\Admin\Controller;

use XF\Mvc\FormAction;

class User extends XFCP_User
{
    /**
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpMissingReturnTypeInspection
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function userSaveProcess(\XF\Entity\User $user)
    {
        $formAction = parent::userSaveProcess($user);

        $formAction->apply(function (FormAction $form) use ($user)
        {
            /** @var \LiamW\XenForoLicenseVerification\XF\Entity\User $user */
            if ($this->filter('liamw_xenforolicenseverification_remove_license', 'bool') === true && $user->XenForoLicense)
            {
                $removeCustomerToken = $this->filter('liamw_xenforolicenseverification_remove_license_customer_token', 'bool');

                $user->expireValidation($removeCustomerToken);
            }
        });

        return $formAction;
    }
}