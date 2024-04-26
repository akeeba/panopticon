# WordPress integration

## Models
* Site Model
  * [ ] getExtensionsQuickInfo: update for WordPress
  * [ ] getWordPressUpdateRunState: similar to getJoomlaUpdateRunState but for WordPress -- Needs UI changes
  * [ ] canRefreshCoreWordPressFiles: similar to canRefreshCoreJoomlaFiles but for WordPress -- Needs UI changes

## Controllers
* Sites
  * [ ] scheduleWordPressUpdate: similar to scheduleJoomlaUpdate
  * [ ] unscheduleWordPressUpdate: similar to unscheduleJoomlaUpdate
  * [ ] schedulePluginUpdate: similar to scheduleExtensionUpdate

## Tasks

* [x] JoomlaUpdate: reject non-Joomla sites
* [x] ExtensionsUpdate: reject non-Joomla sites
* [ ] JoomlaUpdateDirector: only look for sites with Joomla, or no designation
* [ ] ExtensionsUpdate: : only look for sites with Joomla, or no designation
* [ ] RefreshSiteInfo: Split between Joomla! and WordPress, and handle accordingly
* [ ] RefreshInstalledExtensions: Split between Joomla! and WordPress, and handle accordingly
* [ ] WordPressUpdate task (similar to JoomlaUpdate task)
* [ ] WordPressUpdateDirector task (similar to JoomlaUpdateDirector task)
* [ ] Add WordPressUpdateDirector to \Akeeba\Panopticon\Model\Setup::DEFAULT_TASKS
* [ ] PluginsUpdate task (similar to ExtensionsUpdate task)
* [ ] WordPressPluginsUpdatesDirector task (similar to ExtensionUpdatesDirector task)
* [ ] Add WordPressPluginsUpdatesDirector to WordPressUpdateDirector
* [ ] AkeebaBackup task: work with WordPress
* [ ] FileScanner task: work with WordPress

## CLI

* [ ] SiteAdd needs to specify CMS type, default to Joomla!
* [ ] SiteOverridesList: decline for WP sites
* [ ] TaskPluginsUpdatesDirector like TaskExtensionUpdatesDirector
* [ ] TaskWordPressUpdateDirector like TaskJoomlaUpdatesDirector

## Internals

* [ ] EnqueueWordPressUpdateTrait: similar to EnqueueJoomlaUpdateTrait (method: enqueueWordPressUpdate)
* [ ] EnqueuePluginsUpdateTrait: similar to EnqueueExtensionUpdateTrait (method: schedulePluginsUpdateForSite)
* [ ] AdminToolsIntegrationTrait: deal with WP
* [ ] AkeebaBackupIntegrationTrait: deal with WP




# Documentation notes

## Pages in need of documentation

* Mail templates
* Global configuration
* Overview
