# OXRUN CHANGELOG

## v0.6.7

* fix for exceptionlog command with new log file name and structure
  since OXID 6.0.3

## v0.6.6

* fix for loading from modules dir if namespace does not match folder name(s)
* finally removed deprecated shop install command, not relevant for OXID >= 6.0

## v0.6.5

* added possibility to load custom commands from module folders
* removed some deprecated code and files

## v0.6.4

* removed deprecated shop install command, not relevant for OXID >= 6.0
* removed guzzlehttp dependency

## v0.6.3

* only cleanup after module fix if not resetting

## v0.6.2

* OXID 6 compat improved
* More OXID6 compat, use namespace for ModuleList class
* Fix aModuleControllers, too

## v0.6.1

* Updated PHAR
* Changes for latest oxid_modules_config export and import commands
* Added interactive user:create command, updated phar and README

## v0.5.0

* Fix if oxruncmds dir doesn't exists
* Fix autoload
* Add autoloading of custom "oxruncmds" folder in OXID source dir

## v0.4.0

* Added composer install details
* Also include TRACE messages not starting with #, but with [line ...
* Fix FILE regex for log viewer to include smarty compiled names
* Added log:exceptionlog command
* Fix shopid, run reset exclusively
* Fix for using shopId with blacklist multi module activation, add reset option for module:fix command
* Implement module:fix command, taken from OXID Console project

## v0.2.7

* Use bleeding edge modulesconfig module (PR branch)
