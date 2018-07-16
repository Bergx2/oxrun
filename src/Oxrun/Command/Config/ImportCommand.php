<?php

namespace Oxrun\Command\Config;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use \OxidProfessionalServices\ModulesConfig\Core\ConfigImport;

/**
 * Class ImportCommand
 * @package Oxrun\Command\Config
 */
class ImportCommand extends Command
{

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('config:import')
            ->setDescription('Import shop config')
            ->addOption(
                'no-debug',
                null, //can not use n
                InputOption::VALUE_NONE,
                'No debug ouput',
                null
            )
            ->addOption(
                'env',
                null,
                InputOption::VALUE_OPTIONAL,
                'set specific environment, corresponds to a specific folder for the yaml files',
                null
            )
            ->addOption(
                'force-cleanup',
                null,
                InputOption::VALUE_OPTIONAL,
                'Force cleanup on error',
                null
            );
        $help = <<<HELP
<info>Info:</info>
Imports all config values from yaml files, interacts with the
[Modules Config](https://github.com/OXIDprojects/oxid_modules_config/) module
HELP;
        $this->setHelp($help);
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $oConfigImport = oxNew(ConfigImport::class, $output, $input);
        $oConfigImport->executeConsoleCommand();
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getApplication()->bootstrapOxid();
    }
}
