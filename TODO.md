# WordPress integration

## Models
* Site Model
  * [x] buildQuery: add cmsType filter
  * [x] \Akeeba\Panopticon\Model\Site::check: check cmsType, assign `joomla` if nothing else is chosen.
  * [ ] getAdminUrl: update for WordPress
  * [ ] fixCoreUpdateSite: n/a for WP
  * [ ] getExtensionsUpdateTask: should return PluginsUpdate for WordPress
  * [ ] getPluginsUpdateTask: alias to getExtensionsUpdateTask 
  * [ ] getJoomlaUpdateTask: should return WordPressUpdate for WordPress
  * [ ] getWordPressUpdateTask: alias to getJoomlaUpdateTask
  * [ ] isExtensionsUpdateTaskStuck: should use PluginsUpdate for WordPress
  * [ ] isPluginsUpdateTaskStuck: alias to isExtensionsUpdateTaskStuck
  * [ ] isJoomlaUpdateTaskStuck: should use WordPressUpdate for WordPress
  * [ ] isWordPressUpdateTaskStuck: alias to isJoomlaUpdateTaskStuck
  * [ ] isExtensionsUpdateTaskScheduled: should use PluginsUpdate for WordPress
  * [ ] isPluginsUpdateTaskScheduled: alias to isExtensionsUpdateTaskScheduled
  * [ ] isJoomlaUpdateTaskScheduled: should use WordPressUpdate for WordPress
  * [ ] isWordPressUpdateTaskScheduled: alias to isJoomlaUpdateTaskScheduled
  * [ ] isJoomlaUpdateTaskRunning: should use WordPressUpdate for WordPress
  * [ ] isWordPressUpdateTaskRunning: alias to isJoomlaUpdateTaskRunning
  * [ ] isExtensionsUpdateTaskRunning: should use PluginsUpdate for WordPress
  * [ ] isPluginsUpdateTaskRunning: alias to isExtensionsUpdateTaskRunning
  * [ ] getExtensionsQuickInfo: update for WordPress
  * [ ] saveDownloadKey: n/a for WordPress
  * [ ] getJoomlaUpdateRunState: n/a for WordPress
  * [ ] getWordPressUpdateRunState: similar to getJoomlaUpdateRunState but for WordPress -- Needs UI changes
  * [ ] canRefreshCoreWordPressFiles: similar to canRefreshCoreJoomlaFiles but for WordPress -- Needs UI changes

## Controllers
* Sites
  * [ ] fixJoomlaCoreUpdateSite: n/a for WordPress
  * [ ] scheduleJoomlaUpdate: n/a for WordPress
  * [ ] scheduleWordPressUpdate: similar to scheduleJoomlaUpdate
  * [ ] unscheduleJoomlaUpdate: n/a for WordPress
  * [ ] unscheduleWordPressUpdate: similar to unscheduleJoomlaUpdate
  * [ ] clearUpdateScheduleError: task type change for WordPress
  * [ ] clearExtensionUpdatesScheduleError: task type change for WordPress
  * [ ] resetExtensionUpdate: queue type change for WordPress
  * [ ] scheduleExtensionUpdate: n/a for WordPress
  * [ ] schedulePluginUpdate: similar to scheduleExtensionUpdate
  * [ ] dlkey: n/a for WordPress
  * [ ] savedlkey: n/a for WordPress

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
