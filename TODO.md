# WordPress integration

## UI
* [x] Review Akeeba Backup integration
* [x] Review Admin Tools integration
* [x] Core Updates page
* [x] Extension Updates page

## Controllers
* Sites
  * [x] scheduleWordPressUpdate: similar to scheduleJoomlaUpdate
  * [x] unscheduleWordPressUpdate: similar to unscheduleJoomlaUpdate
  * [x] schedulePluginUpdate: similar to scheduleExtensionUpdate

## Tasks

* [x] WordPressUpdate task (similar to JoomlaUpdate task)
* [x] WordPressUpdateDirector task (similar to JoomlaUpdateDirector task)
* [x] Add WordPressUpdateDirector to \Akeeba\Panopticon\Model\Setup::DEFAULT_TASKS
* [x] PluginsUpdate task (similar to ExtensionsUpdate task)
* [x] PluginUpdatesDirector task (similar to ExtensionUpdatesDirector task)
* [x] Add PluginsUpdatesDirector to \Akeeba\Panopticon\Model\Setup::DEFAULT_TASKS
* [x] AkeebaBackup task: work with WordPress
* [x] FileScanner task: work with WordPress

## Mail Templates
* [x] wordpressupdate_found
* [x] wordpressupdate_installed
* [x] wordpressupdate_failed
* [x] wordpressupdate_will_install
* [x] plugin_update_found
* [x] plugins_update_done  (plus view template)

## CLI

* [x] SiteAdd needs to specify CMS type, default to Joomla!
* [x] SiteOverridesList: decline for WP sites
* [x] SiteUpdateWordPress calls WordPressUpdate task
* [x] SiteUpdatePlugins calls PluginsUpdate task
* [x] TaskWordPressUpdateDirector like TaskJoomlaUpdateDirector
* [x] TaskPluginUpdatesDirector like TaskExtensionUpdatesDirector

## Internals

* [x] EnqueueWordPressUpdateTrait: similar to EnqueueJoomlaUpdateTrait (method: enqueueWordPressUpdate)
* [x] EnqueuePluginUpdateTrait: similar to EnqueueExtensionUpdateTrait (method: schedulePluginsUpdateForSite)
* [x] Model\AkeebaBackupIntegrationTrait: deal with WP
* [x] Controller\AkeebaBackupIntegrationTrait: deal with WP
* [x] Model\AdminToolsIntegrationTrait: deal with WP
* [x] Controller\AdminToolsIntegrationTrait: deal with WP

# WordPress plugin and theme keys

Plugins: `plg_ID` e.g. `plg_admintoolswp/admintoolswp.php`

Themes: `tpl_ID` e.g. `tpl_twentytwenty`

# Documentation notes

## Pages in need of documentation

* Mail templates
* Global configuration
* Overview
