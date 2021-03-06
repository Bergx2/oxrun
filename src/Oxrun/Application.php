<?php
/*
 * Created for oxrun

 * Some code in this file was taken from OXID Console package.
 * See https://github.com/OXIDprojects/oxid-console/
 *
 * (c) Eligijus Vitkauskas <eligijusvitkauskas@gmail.com>
 */

namespace Oxrun;

use Composer\Autoload\ClassLoader;
use Oxrun\Command\Custom;
use Oxrun\Helper\DatabaseConnection;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class Application
 * @package Oxrun
 */
class Application extends BaseApplication
{
    /**
     * @var null
     */
    protected $oxidBootstrapExists = null;

    /**
     * @var null
     */
    protected $hasDBConnection = null;

    /**
     * Oxid eshop shop dir
     *
     * @var string
     */
    protected $shopDir;

    /**
     * @var ClassLoader|null
     */
    protected $autoloader;

    /**
     * @var string
     */
    protected $oxidConfigContent;

    /**
     * @var databaseConnection
     */
    protected $databaseConnection = null;

    /**
     * @var string
     */
    protected $oxid_version = "0.0.0";

    /**
     * @param ClassLoader   $autoloader The composer autoloader
     * @param string        $name
     * @param string        $version
     */
    public function __construct($autoloader = null, $name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        $this->autoloader = $autoloader;
        parent::__construct($name, $version);
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultCommands()
    {
        return array(
            new HelpCommand(),
            new Custom\ListCommand(),
        );
    }


    /**
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        $inputDefinition = parent::getDefaultInputDefinition();

        $shopDirOption = new InputOption(
            '--shopDir',
            '',
            InputOption::VALUE_OPTIONAL,
            'Force oxid base dir. No auto detection'
        );
        $inputDefinition->addOption($shopDirOption);

        return $inputDefinition;
    }

    /**
     * Oxid bootstrap.php is loaded.
     *
     * @param bool $blNeedDBConnection this Command need a DB Connection
     *
     * @return bool|null
     */
    public function bootstrapOxid($blNeedDBConnection = true)
    {
        if ($this->oxidBootstrapExists === null) {
            $this->oxidBootstrapExists = $this->findBootstrapFile();
        }

        if ($this->oxidBootstrapExists && $blNeedDBConnection) {
            return $this->canConnectToDB();
        }

        return $this->oxidBootstrapExists;
    }

    /**
     * Search Oxid Bootstrap.file and include that
     *
     * @return bool
     */
    protected function findBootstrapFile()
    {
        $input = new ArgvInput();
        if ($input->getParameterOption('--shopDir')) {
            $oxBootstrap = $input->getParameterOption('--shopDir'). '/bootstrap.php';
            if ($this->checkBootstrapOxidInclude($oxBootstrap) === true) {
                return true;
            }
            return false;
        }

        // try to guess where bootstrap.php is
        $currentWorkingDirectory = getcwd();
        do {
            $oxBootstrap = $currentWorkingDirectory . '/bootstrap.php';
            if ($this->checkBootstrapOxidInclude($oxBootstrap) === true) {
                return true;
                break;
            }
            $currentWorkingDirectory = dirname($currentWorkingDirectory);
        } while ($currentWorkingDirectory !== '/');
        return false;
    }

    /**
     * Check if bootstrap file exists
     *
     * @param String $oxBootstrap Path to oxid bootstrap.php
     * @param bool   $skipViews   Add 'blSkipViewUsage' to OXIDs config.
     *
     * @return bool
     */
    public function checkBootstrapOxidInclude($oxBootstrap)
    {
        if (is_file($oxBootstrap)) {
            // is it the oxid bootstrap.php?
            if (strpos(file_get_contents($oxBootstrap), 'OX_BASE_PATH') !== false) {
                $this->shopDir = dirname($oxBootstrap);

                include_once $oxBootstrap;

                // If we've an autoloader we must re-register it to avoid conflicts with a composer autoloader from shop
                if (null !== $this->autoloader) {
                    $this->autoloader->unregister();
                    $this->autoloader->register(true);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Add custom command folder in OXID source directory
     *
     * @return void
     */
    public function addCustomCommandDir()
    {
        // always add modules dir
        $this->addModulesCommandDirs();

        $commandSourceDir          = __DIR__ . '/../../../../../source/oxruncmds';
        if (!file_exists($commandSourceDir)) {
            return;
        }
        $recursiveIteratorIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandSourceDir));
        $regexIterator             = new \RegexIterator($recursiveIteratorIterator, '/.*Command\.php$/');
        
        foreach ($regexIterator as $commandPath) {
            $commandClass = str_replace(array($commandSourceDir, '/', '.php'), array('', '\\', ''), $commandPath);
            if (!class_exists($commandClass)) {
                echo "\nClass $commandClass does not exist!\n";
                continue;
            }
            try {
                $instance = new $commandClass;
                if ($this->isCommandCompatibleClass($instance)) {
                    $this->add($instance);
                }
            } catch (\Exception $ex) {
                echo "\nError loading class $commandClass!\n";
            }
        }
    }

    /**
     * Add modules folder in OXID source directory
     * Every module may have a subfolder "[C|c]ommand[s]" containing
     * oxrun commands which we try to load here
     *
     * @return void
     */
    protected function addModulesCommandDirs()
    {
        $config = Registry::getConfig();
        $modulesRootPath = $config->getModulesDir();
        $paths = $this->getPathsOfAvailableModules();
        $pathToPhpFiles = $this->getPhpFilesMatchingPatternForCommandFromGivenPaths(
            $paths
        );
        $classes = $this->getAllClassesFromPhpFiles($pathToPhpFiles);
        foreach ($classes as $commandClass) {
            if (!class_exists($commandClass)) {
                echo "\nClass $commandClass does not exist!\n";
                continue;
            }
            try {
                $instance = new $commandClass;
                if ($this->isCommandCompatibleClass($instance)) {
                    $this->add($instance);
                }
            } catch (\Exception $ex) {
                echo "\nError loading class $commandClass!\n";
            }
        }
    }

    /**
     * @return mixed|string
     * @throws \Exception
     */
    public function getOxidVersion()
    {
        if ($this->oxid_version != '0.0.0') {
            return $this->oxid_version;
        }

        $this->findVersionOnOxid6();

        return $this->oxid_version;
    }

    /**
     * @return string
     */
    public function getShopDir()
    {
        return $this->shopDir;
    }

    /**
     * @return bool
     */
    public function canConnectToDB()
    {
        if ($this->hasDBConnection !== null) {
            return $this->hasDBConnection;
        }

        $configfile = $this->shopDir . DIRECTORY_SEPARATOR . 'config.inc.php';

        if ($this->shopDir && file_exists($configfile)) {
            $oxConfigFile = new \OxConfigFile($configfile);

            $databaseConnection = $this->getDatabaseConnection();
            $databaseConnection
                ->setHost($oxConfigFile->getVar('dbHost'))
                ->setUser($oxConfigFile->getVar('dbUser'))
                ->setPass($oxConfigFile->getVar('dbPwd'))
                ->setDatabase($oxConfigFile->getVar('dbName'));

            return $this->hasDBConnection = $databaseConnection->canConnectToMysql();
        }

        return $this->hasDBConnection = false;
    }

    /**
     * @return DatabaseConnection
     */
    public function getDatabaseConnection()
    {
        if ($this->databaseConnection === null) {
            $this->databaseConnection = new DatabaseConnection();
        }

        return $this->databaseConnection;
    }

    /**
     * Completely switch shop
     *
     * @param string $shopId The shop id
     *
     * @return void
     */
    public function switchToShopId($shopId)
    {
        $_POST['shp'] = $shopId;
        $_POST['actshop'] = $shopId;
        
        $keepThese = [\OxidEsales\Eshop\Core\ConfigFile::class];
        $registryKeys = \OxidEsales\Eshop\Core\Registry::getKeys();
        foreach ($registryKeys as $key) {
            if (in_array($key, $keepThese)) {
                continue;
            }
            \OxidEsales\Eshop\Core\Registry::set($key, null);
        }

        $utilsObject = new \OxidEsales\Eshop\Core\UtilsObject;
        $utilsObject->resetInstanceCache();
        \OxidEsales\Eshop\Core\Registry::set(\OxidEsales\Eshop\Core\UtilsObject::class, $utilsObject);

        \OxidEsales\Eshop\Core\Module\ModuleVariablesLocator::resetModuleVariables();
        \OxidEsales\Eshop\Core\Registry::getSession()->setVariable('shp', $shopId);

        //ensure we get rid of all instances of config, even the one in Core\Base
        \OxidEsales\Eshop\Core\Registry::set(\OxidEsales\Eshop\Core\Config::class, null);
        \OxidEsales\Eshop\Core\Registry::getConfig()->setConfig(null);
        \OxidEsales\Eshop\Core\Registry::set(\OxidEsales\Eshop\Core\Config::class, null);

        $moduleVariablesCache = new \OxidEsales\Eshop\Core\FileCache();
        $shopIdCalculator = new \OxidEsales\Eshop\Core\ShopIdCalculator($moduleVariablesCache);

        if (($shopId != $shopIdCalculator->getShopId())
            || ($shopId != \OxidEsales\Eshop\Core\Registry::getConfig()->getShopId())
        ) {
            throw new \Exception('Failed to switch to subshop id ' . $shopId . " Calculate ID: " . $shopIdCalculator->getShopId() . " Config ShopId: " . \OxidEsales\Eshop\Core\Registry::getConfig()->getShopId());
        }
    }

    /**
     * Get YAML string, either from file or from string
     *
     * @param string $ymlString The relative file path, from shop root OR a YAML string
     * @param string $basePath  Alternative root dir path, if a file is used
     *
     * @return string
     */
    public function getYaml($ymlString, $basePath = '')
    {
        // is it a file?
        if (strpos(strtolower($ymlString), '.yml') !== false
            || strpos(strtolower($ymlString), '.yaml') !== false
        ) {
            if ($basePath == '') {
                $basePath = $this->getShopDir() . DIRECTORY_SEPARATOR;
            }
            $ymlFile = $basePath . $ymlString;
            if (file_exists($ymlFile)) {
                $ymlString = file_get_contents($ymlFile);
            }
        }
        
        return $ymlString;
    }
        
    /**
     * Find Version up to OXID 6 Version
     * @throws \Exception
     */
    protected function findVersionOnOxid6()
    {
        if (!class_exists('OxidEsales\\Eshop\\Core\\ShopVersion')) {
            throw new \Exception('Can\'t find Shop Version. Maybe run OXID `Unified Namespace Generator` with composer');
        }

        $this->oxid_version = \OxidEsales\Eshop\Core\ShopVersion::getVersion();
    }

    /**
     * Filter out classes with predefined criteria to be accepted as valid `Command` classes.
     *
     * A given class should match the following criteria:
     *   a) Extends `Symfony\Component\Console\Command\Command`;
     *   b) Is not `Symfony\Component\Console\Command\Command` itself.
     *
     * @param string $class
     *
     * @return boolean
     */
    private function isCommandCompatibleClass($class)
    {
        return is_subclass_of($class, Command::class) && $class !== Command::class;
    }
    /**
     * Return list of paths to all available modules.
     *
     * @return string[]
     */
    private function getPathsOfAvailableModules()
    {
        $config = Registry::getConfig();
        $modulesRootPath = $config->getModulesDir();
        $modulePaths = $config->getConfigParam('aModulePaths');
        if (!is_dir($modulesRootPath)) {
            return [];
        }
        if (!is_array($modulePaths)) {
            return [];
        }
        $fullModulePaths = array_map(function ($modulePath) use ($modulesRootPath) {
            return $modulesRootPath . $modulePath;
        }, array_values($modulePaths));
        return array_filter($fullModulePaths, function ($fullModulePath) {
            return is_dir($fullModulePath);
        });
    }
    /**
     * Return list of PHP files matching `Command` specific pattern.
     *
     * @param string $path Path to collect files from
     *
     * @return string[]
     */
    private function getPhpFilesMatchingPatternForCommandFromGivenPath($path)
    {
        $folders = ['Commands','commands','Command'];
        foreach ($folders as $f) {
            $cPath = $path . DIRECTORY_SEPARATOR . $f . DIRECTORY_SEPARATOR;
            if (!is_dir($cPath)) {
                continue;
            }
            $files = glob("$cPath*[cC]ommand\.php");
            return $files;
        }
        return [];
    }
    /**
     * Convert array of arrays to flat list array.
     *
     * @param array[] $nonFlatArray
     *
     * @return array
     */
    private function getFlatArray($nonFlatArray)
    {
        return array_reduce($nonFlatArray, 'array_merge', []);
    }
    /**
     * Helper method for `getPhpFilesMatchingPatternForCommandFromGivenPath`
     *
     * @param string[] $paths
     *
     * @return string[]
     */
    private function getPhpFilesMatchingPatternForCommandFromGivenPaths($paths)
    {
        return $this->getFlatArray(array_map(function ($path) {
            return $this->getPhpFilesMatchingPatternForCommandFromGivenPath($path);
        }, $paths));
    }
    /**
     * Get list of defined classes from given PHP file.
     *
     * @param string $pathToPhpFile
     *
     * @return string[]
     */
    private function getAllClassesFromPhpFile($pathToPhpFile)
    {
        $classesBefore = get_declared_classes();
        try {
            require_once $pathToPhpFile;
        } catch (\Throwable $exception) {
            print "Can not add Command $pathToPhpFile:\n";
            print $exception->getMessage() . "\n";
        }
        $classesAfter = get_declared_classes();
        $newClasses = array_diff($classesAfter, $classesBefore);
        if (count($newClasses) > 1) {
            //try to find the correct class name to use
            //this avoids warnings when module developer use there own command base class, that is not instantiable
            $name = basename($pathToPhpFile, '.php');
            foreach ($newClasses as $newClass) {
                if ($newClass == $name) {
                    return [$newClass];
                }
            }
        }
        return $newClasses;
    }
    /**
     * Helper method for `getAllClassesFromPhpFile`
     *
     * @param string[] $pathToPhpFiles
     *
     * @return string[]
     */
    private function getAllClassesFromPhpFiles($pathToPhpFiles)
    {
        return $this->getFlatArray(array_map(function ($pathToPhpFile) {
            return $this->getAllClassesFromPhpFile($pathToPhpFile);
        }, $pathToPhpFiles));
    }
}
