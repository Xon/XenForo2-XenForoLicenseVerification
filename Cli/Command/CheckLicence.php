<?php

namespace LiamW\XenForoLicenseVerification\Cli\Command;

use LiamW\XenForoLicenseVerification\Service\XenForoLicense\Verifier as VerifierService;
use SV\StandardLib\Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckLicence extends Command
{
    protected function configure()
    {
        $this
            ->setName('licence-xf:check')
            ->setDescription('Performs a xenforo licence check')
            ->addArgument(
                'validation_token',
                InputArgument::REQUIRED,
                'The validation token to check'
            )
            ->addArgument(
                'domain',
                InputArgument::OPTIONAL,
                'Optional domain to check'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $validationToken = $input->getArgument('validation_token') ?? '';

        if (\strlen($validationToken) === 0)
        {
            $output->writeln('<error>User has no xenforo licence</error>');

            return 1;
        }

        $domain = $input->getArgument('domain') ?? '';

        $validationService = Helper::service(VerifierService::class, null, $validationToken, $domain);

        if (!$validationService->isValid($error))
        {
            $output->writeln('<error>' . $error . '</error>');

            return 1;
        }

        $output->writeln('Valid licence for; ' . $validationToken . ' - ' . $domain);

        return 0;
    }
}