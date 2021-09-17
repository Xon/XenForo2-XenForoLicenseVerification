<?php

namespace LiamW\XenForoLicenseVerification\Cli\Command;

use LiamW\XenForoLicenseVerification\XF\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckUserLicence extends Command
{
    protected function configure()
    {
        $this
            ->setName('licence-xf:user-check')
            ->setDescription('Performs a xenforo licence check')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'User (or email of user) to check the XF licence status for'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');

        /** @var \XF\Repository\User $userRepo */
        $userRepo = \XF::repository('XF:User');
        /** @var ?User $user */
        $user = $userRepo->getUserByNameOrEmail($username);
        if ($user === null)
        {
            $output->writeln('<error>User not found</error>');

            return 1;
        }

        $licence = $user->XenForoLicense;
        if ($licence === null || $licence->validation_token === null)
        {
            $output->writeln('<error>User has no xenforo licence</error>');

            return 1;
        }

        /** @var \LiamW\XenForoLicenseVerification\Service\XenForoLicense\Verifier $validationService */
        $validationService = \XF::service('LiamW\XenForoLicenseVerification:XenForoLicense\Verifier', $user, $licence->validation_token, $licence->domain);

        if (!$validationService->isValid($error))
        {
            $output->writeln('<error>' . $error . '</error>');

            return 1;
        }

        $output->writeln('Valid licence for; ' . $user->username . ' - ' . $licence->validation_token . ' - ' . $licence->domain);

        return 0;
    }
}