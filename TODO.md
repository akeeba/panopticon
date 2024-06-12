# WordPress integration

## UI
* [ ] Review Akeeba Backup integration
* [ ] Review Admin Tools integration

## Controllers
* Sites
  * [ ] scheduleWordPressUpdate: similar to scheduleJoomlaUpdate
  * [ ] unscheduleWordPressUpdate: similar to unscheduleJoomlaUpdate
  * [ ] schedulePluginUpdate: similar to scheduleExtensionUpdate

## Tasks

* [x] WordPressUpdate task (similar to JoomlaUpdate task)
* [ ] WordPressUpdateDirector task (similar to JoomlaUpdateDirector task)
* [ ] Add WordPressUpdateDirector to \Akeeba\Panopticon\Model\Setup::DEFAULT_TASKS
* [ ] PluginsUpdate task (similar to ExtensionsUpdate task)
* [ ] WordPressPluginsUpdatesDirector task (similar to ExtensionUpdatesDirector task)
* [ ] Add WordPressPluginsUpdatesDirector to WordPressUpdateDirector
* [ ] AkeebaBackup task: work with WordPress
* [ ] FileScanner task: work with WordPress

## Mail Templates
* [ ] wordpressupdate_installed
* [ ] wordpressupdate_failed

## CLI

* [ ] SiteOverridesList: decline for WP sites
* [ ] SiteAdd needs to specify CMS type, default to Joomla!
* [X] SiteUpdateWordPress calls WordPressUpdate task
* [ ] SiteUpdatePlugins calls PluginsUpdate task
* [ ] TaskPluginsUpdatesDirector like TaskExtensionUpdatesDirector
* [ ] TaskWordPressUpdateDirector like TaskJoomlaUpdatesDirector

## Internals

* [X] EnqueueWordPressUpdateTrait: similar to EnqueueJoomlaUpdateTrait (method: enqueueWordPressUpdate)
* [ ] EnqueuePluginsUpdateTrait: similar to EnqueueExtensionUpdateTrait (method: schedulePluginsUpdateForSite)
* [ ] Model\AdminToolsIntegrationTrait: deal with WP
* [ ] Controller\AdminToolsIntegrationTrait: deal with WP
* [ ] Model\AkeebaBackupIntegrationTrait: deal with WP
* [ ] Controller\AkeebaBackupIntegrationTrait: deal with WP



# WordPress plugin and theme keys

Plugins: `plg_ID` e.g. `plg_admintoolswp/admintoolswp.php`

Themes: `tpl_ID` e.g. `tpl_twentytwenty`


# Documentation notes

## Pages in need of documentation

* Mail templates
* Global configuration
* Overview
