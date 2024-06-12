# WordPress integration

## UI
* [ ] Review Akeeba Backup integration
* [ ] Review Admin Tools integration

## Controllers
* Sites
  * [x] scheduleWordPressUpdate: similar to scheduleJoomlaUpdate
  * [x] unscheduleWordPressUpdate: similar to unscheduleJoomlaUpdate
  * [ ] schedulePluginUpdate: similar to scheduleExtensionUpdate

## Tasks

* [x] WordPressUpdate task (similar to JoomlaUpdate task)
* [X] WordPressUpdateDirector task (similar to JoomlaUpdateDirector task)
* [X] Add WordPressUpdateDirector to \Akeeba\Panopticon\Model\Setup::DEFAULT_TASKS
* [ ] PluginsUpdate task (similar to ExtensionsUpdate task)
* [ ] WordPressPluginsUpdatesDirector task (similar to ExtensionUpdatesDirector task)
* [ ] Add WordPressPluginsUpdatesDirector to WordPressUpdateDirector
* [ ] AkeebaBackup task: work with WordPress
* [ ] FileScanner task: work with WordPress

## Mail Templates
* [x] wordpressupdate_found
* [x] wordpressupdate_installed
* [x] wordpressupdate_failed
* [x] wordpressupdate_will_install

## CLI

* [ ] SiteOverridesList: decline for WP sites
* [ ] SiteAdd needs to specify CMS type, default to Joomla!
* [X] SiteUpdateWordPress calls WordPressUpdate task
* [ ] SiteUpdatePlugins calls PluginsUpdate task
* [X] TaskWordPressUpdateDirector like TaskJoomlaUpdateDirector
* [ ] TaskPluginsUpdatesDirector like TaskExtensionUpdatesDirector

## Internals

* [X] EnqueueWordPressUpdateTrait: similar to EnqueueJoomlaUpdateTrait (method: enqueueWordPressUpdate)
* [ ] EnqueuePluginsUpdateTrait: similar to EnqueueExtensionUpdateTrait (method: schedulePluginsUpdateForSite)
* [ ] Model\AkeebaBackupIntegrationTrait: deal with WP
* [ ] Controller\AkeebaBackupIntegrationTrait: deal with WP
* [ ] Model\AdminToolsIntegrationTrait: deal with WP
* [ ] Controller\AdminToolsIntegrationTrait: deal with WP



# WordPress plugin and theme keys

Plugins: `plg_ID` e.g. `plg_admintoolswp/admintoolswp.php`

Themes: `tpl_ID` e.g. `tpl_twentytwenty`


# Documentation notes

## Pages in need of documentation

* Mail templates
* Global configuration
* Overview
