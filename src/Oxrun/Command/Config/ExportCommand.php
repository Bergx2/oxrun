<?php

namespace Oxrun\Command\Config;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use \OxidProfessionalServices\ModulesConfig\Core\ConfigExport as ConfigExport;

/**
 * Class ExportCommand
 * @package Oxrun\Command\Config
 */
class ExportCommand extends Command
{

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('config:export')
            ->setDescription('Export shop config')
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
Exports all config values to yaml files, interacts with the
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
        $oConfigExport = oxNew(ConfigExport::class, $output, $input);
        $oConfigExport->executeConsoleCommand();
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getApplication()->bootstrapOxid();
    }
}
