# WordPress integration

## Models
* Site Model
  * [x] buildQuery: add cmsType filter
  * [x] \Akeeba\Panopticon\Model\Site::check: check cmsType, assign `joomla` if nothing else is chosen.
  * [x] \Akeeba\Panopticon\Model\Site::getAdminUrl update for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::fixCoreUpdateSite can't run if not Joomla
  * [x] getExtensionsUpdateTask: should return PluginsUpdate for WordPress
  * [x] getPluginsUpdateTask: alias to getExtensionsUpdateTask 
  * [x] \Akeeba\Panopticon\Model\Site::getJoomlaUpdateTask: should return WordPressUpdate for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::getWordPressUpdateTask: alias to getJoomlaUpdateTask
  * [x] \Akeeba\Panopticon\Model\Site::isExtensionsUpdateTaskStuck: should use PluginsUpdate for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::isPluginsUpdateTaskStuck: alias to isExtensionsUpdateTaskStuck
  * [x] \Akeeba\Panopticon\Model\Site::isJoomlaUpdateTaskStuck: should use WordPressUpdate for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::isWordPressUpdateTaskStuck: alias to isJoomlaUpdateTaskStuck
  * [x] \Akeeba\Panopticon\Model\Site::isExtensionsUpdateTaskScheduled: should use PluginsUpdate for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::isPluginsUpdateTaskScheduled: alias to isExtensionsUpdateTaskScheduled
  * [x] \Akeeba\Panopticon\Model\Site::isJoomlaUpdateTaskScheduled: should use WordPressUpdate for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::isWordPressUpdateTaskScheduled: alias to isJoomlaUpdateTaskScheduled
  * [x] \Akeeba\Panopticon\Model\Site::isJoomlaUpdateTaskRunning: should use WordPressUpdate for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::isWordPressUpdateTaskRunning: alias to isJoomlaUpdateTaskRunning
  * [x] \Akeeba\Panopticon\Model\Site::isExtensionsUpdateTaskRunning: should use PluginsUpdate for WordPress
  * [x] \Akeeba\Panopticon\Model\Site::isPluginsUpdateTaskRunning: alias to isExtensionsUpdateTaskRunning
  * [x] \Akeeba\Panopticon\Model\Site::saveDownloadKey only works for Joomla
  * [x] \Akeeba\Panopticon\Model\Site::getJoomlaUpdateRunState only works for Joomla
  * [ ] getExtensionsQuickInfo: update for WordPress
  * [ ] getWordPressUpdateRunState: similar to getJoomlaUpdateRunState but for WordPress -- Needs UI changes
  * [ ] canRefreshCoreWordPressFiles: similar to canRefreshCoreJoomlaFiles but for WordPress -- Needs UI changes

## Controllers
* Sites
  * [x] fixJoomlaCoreUpdateSite: n/a for WordPress
  * [x] scheduleJoomlaUpdate: n/a for WordPress
  * [x] unscheduleJoomlaUpdate: n/a for WordPress
  * [x] scheduleExtensionUpdate: n/a for WordPress
  * [x] dlkey: n/a for WordPress
  * [x] savedlkey: n/a for WordPress
  * [ ] scheduleWordPressUpdate: similar to scheduleJoomlaUpdate
  * [ ] unscheduleWordPressUpdate: similar to unscheduleJoomlaUpdate
  * [ ] clearUpdateScheduleError: task type change for WordPress
  * [ ] clearExtensionUpdatesScheduleError: task type change for WordPress
  * [ ] resetExtensionUpdate: queue type change for WordPress
  * [ ] schedulePluginUpdate: similar to scheduleExtensionUpdate

## Tasks

* [ ] JoomlaUpdate: reject non-Joomla sites
* [ ] ExtensionsUpdate: reject non-Joomla sites
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

* [ ] AdminToolsIntegrationTrait: deal with WP
* [ ] AkeebaBackupIntegrationTrait: deal with WP
* [ ] EnqueueWordPressUpdateTrait: similar to EnqueueJoomlaUpdateTrait

# Documentation notes

## Pages in need of documentation

* Mail templates
* Global configuration
* Overview
