<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Registry\Registry;

$user                 = $this->container->userManager->getUser();
$config               = $this->item->getConfig();
$token                = $this->container->session->getCsrfToken()->getValue();
$extensions           = $this->item->getExtensionsList();
$extensionsUpdateTask = $this->item->getExtensionsUpdateTask();
$scheduledExtensions  = $this->item->getExtensionsScheduledForUpdate();

$lastUpdateTimestamp = function () use ($config): string
{
	$timestamp = $config->get('extensions.lastAttempt');

	return $timestamp ? $this->timeAgo($timestamp) : \Awf\Text\Text::_('PANOPTICON_LBL_NEVER');
};

$willAutoUpdate = function (string $key, ?string $oldVersion, ?string $newVersion): bool
{
	static $updateInfo, $globalUpdateInfo, $defaultPreference;

	if (empty($oldVersion) || empty($newVersion) || empty($key) || version_compare($oldVersion, $newVersion, 'ge'))
	{
		return false;
	}

	if ($updateInfo === null)
	{
		$updateInfo        = $this->getModel('Sysconfig')->getExtensionPreferencesAndMeta($this->item->id);
		$globalUpdateInfo  = $this->getModel('Sysconfig')->getExtensionPreferencesAndMeta();
		$defaultPreference = $this->container->appConfig->get('tasks_extupdate_install', 'none');
	}

	$updatePreference = ($updateInfo[$key]?->preference ?? '') ?: ($globalUpdateInfo[$key]?->preference ?? '') ?: $defaultPreference;
	$vOld             = Version::create($oldVersion);
	$vNew             = Version::create($newVersion);

	return match ($updatePreference)
	{
		default => false,
		'major' => true,
		'minor' => $vOld->major() === $vNew->major(),
		'patch' => $vOld->versionFamily() === $vNew->versionFamily(),
	};
};


$extensionsQuickInfo = call_user_func(function () use ($extensions): object {
	$ret = (object) [
		'update' => 0,
		'key'    => 0,
		'site'   => 0,
	];

	foreach ($extensions as $item)
	{
		$currentVersion    = $item->version?->current;
		$latestVersion     = $item->version?->new;
		$noUpdateSite      = !($item->hasUpdateSites ?? false);
		$missingDownloadID = ($item->downloadkey?->supported ?? false)
			&& !($item->downloadkey?->valid ?? false);
		$hasUpdate         = !empty($currentVersion) && !empty($latestVersion)
			&& ($currentVersion != $latestVersion)
			&& version_compare($currentVersion, $latestVersion, 'lt');

		if ($noUpdateSite) {
			$ret->site++;
        }

		if ($missingDownloadID) {
			$ret->key++;
        }

		if ($hasUpdate) {
			$ret->update++;
        }
	}

    return $ret;
});

$shouldCollapse = $extensionsQuickInfo->update == 0 && $extensionsQuickInfo->site == 0 && $extensionsQuickInfo->key = 0;

?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-cubes" aria-hidden="true"></span>
        <span class="flex-grow-1">
            @lang('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD')
            @if ($extensionsQuickInfo->update > 0)
                <sup>
                    <span class="badge bg-warning" style="font-size: small"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATES_N', $extensionsQuickInfo->update)"
                    >
                        <span class="fa fa-box-open" aria-hidden="true"></span>
                        <span aria-hidden="true">{{{ $extensionsQuickInfo->update  }}}</span>
                        <span class="visually-hidden">@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATES_N', $extensionsQuickInfo->update)</span>
                    </span>
                </sup>
            @endif
            @if ($extensionsQuickInfo->site > 0)
                <sup>
                    <span class="badge bg-warning-subtle" style="font-size: small"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATESITES_N', $extensionsQuickInfo->site)"
                    >
                        <span class="fa fa-globe" aria-hidden="true"></span>
                        <span aria-hidden="true">{{{ $extensionsQuickInfo->site }}}</span>
                        <span class="visually-hidden">@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATESITES_N', $extensionsQuickInfo->site)</span>
                    </span>
                </sup>
            @endif
            @if ($extensionsQuickInfo->key > 0)
                <sup>
                    <span class="badge bg-danger" style="font-size: small"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_NOKEY_N', $extensionsQuickInfo->key)"
                    >
                        <span class="fa fa-key" aria-hidden="true"></span>
                        <span aria-hidden="true">{{{ $extensionsQuickInfo->key }}}</span>
                        <span class="visually-hidden">@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_NOKEY_N', $extensionsQuickInfo->key)</span>
                    </span>
                </sup>
            @endif
        </span>
        <a type="button" class="btn btn-outline-secondary btn-sm" role="button"
           href="@route(sprintf('index.php?view=site&task=refreshExtensionsInformation&id=%d&%s=1', $this->item->id, $token))"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATE_INFO')"
        >
            <span class="fa fa-refresh" aria-hidden="true"></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATE_INFO')
            </span>
        </a>
        <button class="btn btn-success btn-sm ms-2" role="button"
                data-bs-toggle="collapse" href="#cardExtensionsBody"
                aria-expanded="{{ $shouldCollapse ? 'false' : 'true' }}" aria-controls="cardExtensionsBody"
                data-bs-tooltip="tooltip" data-bs-placement="bottom"
                data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
        >
            <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
        </button>
    </h3>
    <div class="card-body collapse{{ $shouldCollapse ? '' : ' show' }}" id="cardExtensionsBody">
        <p class="small text-body-tertiary">
            <strong>
                @lang('PANOPTICON_SITE_LBL_EXTENSIONS_LAST_CHECKED')
            </strong>
            {{ $lastUpdateTimestamp() }}
        </p>

        {{-- Show Update Schedule Information --}}
        @if(!is_null($extensionsUpdateTask))
            @if($extensionsUpdateTask->enabled && $extensionsUpdateTask->last_exit_code == Status::INITIAL_SCHEDULE->value)
                <div class="alert alert-info">
                    <div class="text-center fs-5">
                        <span class="fa fa-clock" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_WILL_RUN')
                    </div>
                </div>
            @elseif ($extensionsUpdateTask->enabled && in_array($extensionsUpdateTask->last_exit_code, [Status::WILL_RESUME->value, Status::RUNNING->value]))
                <div class="alert alert-info">
                    <div class="text-center fs-5">
                        <span class="fa fa-play" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_RUNNING')
                    </div>
                </div>
            @elseif ($extensionsUpdateTask->last_exit_code != Status::OK->value)
                {{-- Task error condition --}}
                <?php
                $status = Status::tryFrom($extensionsUpdateTask->last_exit_code) ?? Status::NO_ROUTINE
                ?>
                <div class="alert alert-danger">
                    <h4 class="h5 alert-heading text-center{{ $status->value === Status::EXCEPTION->value ? ' border-bottom border-danger' : ''  }}">
                        <span class="fa fa-xmark-circle" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_ERRORED')
                        {{ $status->forHumans() }}
                    </h4>

                    @if ($status->value === Status::EXCEPTION->value)
                        <?php
                        $storage = ($extensionsUpdateTask->storage instanceof Registry) ? $extensionsUpdateTask->storage : (new Registry($extensionsUpdateTask->storage));
                        ?>
                        <p>
                            @lang('PANOPTICON_SITE_LBL_JUPDATE_THE_ERROR_REPORTED_WAS')
                        </p>
                        <p class="text-dark">
                            {{{ $storage->get('error') }}}
                        </p>
                        @if (defined('AKEEBADEBUG') && AKEEBADEBUG)
                            <p>@lang('PANOPTICON_SITE_LBL_JUPDATE_ERROR_TRACE')</p>
                            <pre>{{{ $storage->get('trace') }}}</pre>
                        @endif
                    @endif

                    {{-- Button to reset the error (by removing the failed task) --}}
                    <a href="@route(sprintf('index.php?view=site&task=clearExtensionUpdatesScheduleError&id=%d&%s=1', $this->item->id, $token))"
                       class="btn btn-primary mt-3" role="button">
                        <span class="fa fa-eraser" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_JUPDATE_SCHEDULE_CLEAR_ERROR')
                    </a>
                </div>
            @endif
        @endif

        <table class="table table-striped table-responsive">
            <thead class="table-dark">
            <tr>
                <th>
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_NAME')
                </th>
                <th>
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_AUTHOR')
                </th>
                <th style="width: 1%">
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_VERSION')
                </th>
            </tr>
            </thead>
            <tbody>
            @foreach($extensions as $extensionId => $item)
					<?php
					$key = $this->getModel('Sysconfig')
								->getExtensionShortname(
									$item->type, $item->element, $item->folder, $item->client_id
								);

					// Hide core extensions which are stupidly only ever marked as top-level extensions on core update.
					if ($this->getModel('Sysconfig')->isExcludedShortname($key))
					{
						continue;
					}

					$currentVersion    = $item->version?->current;
					$latestVersion     = $item->version?->new;
					$noUpdateSite      = !($item->hasUpdateSites ?? false);
					$missingDownloadID = ($item->downloadkey?->supported ?? false)
						&& !($item->downloadkey?->valid ?? false);
					$error             = $noUpdateSite || $missingDownloadID;
					$hasUpdate         = !empty($currentVersion) && !empty($latestVersion)
						&& ($currentVersion != $latestVersion)
						&& version_compare($currentVersion, $latestVersion, 'lt');
					?>
                <tr>
                    <td>
                        <div>
                            <span class="text-body-tertiary pe-2">
                                @if ($item->type === 'component')
                                    <span class="fa fa-puzzle-piece" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="right"
                                          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')</span>
                                @elseif ($item->type === 'file')
                                    <span class="fa fa-file-alt" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="right"
                                          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')</span>
                                @elseif ($item->type === 'library')
                                    <span class="fa fa-book" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="right"
                                          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')</span>
                                @elseif ($item->type === 'package')
                                    <span class="fa fa-boxes-packing" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="right"
                                          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')</span>
                                @elseif ($item->type === 'plugin')
                                    <span class="fa fa-plug" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="right"
                                          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')</span>
                                @elseif ($item->type === 'module')
                                    <span class="fa fa-cube" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="right"
                                          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')</span>
                                @elseif ($item->type === 'template')
                                    <span class="fa fa-paint-brush" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="right"
                                          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')</span>
                                @endif
                            </span>
                            @if ($error)
                                <span class="text-danger fw-medium">
                                    {{{ $item->name }}}
                                </span>
                            @elseif ($hasUpdate)
                                <span class="text-warning-emphasis fw-bold">
                                    {{{ $item->name }}}
                                </span>
                            @else
                                {{{ $item->name }}}
                            @endif

                            @if (in_array($item->extension_id, $scheduledExtensions))
                                <span class="badge bg-success">
                                    <span class="fa fa-hourglass-half" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_SCHEDULED_UPDATE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_SCHEDULED_UPDATE')</span>
                                </span>

                            @elseif ($hasUpdate && !$error && $willAutoUpdate($key, $currentVersion, $latestVersion))
                                <span class="fa fa-magic-wand-sparkles text-success ms-2" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="top"
                                      data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')</span>
                            @elseif ($hasUpdate && $error && $willAutoUpdate($key, $currentVersion, $latestVersion))
                                <span class="fa fa-magic text-danger ms-2" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="top"
                                      data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')</span>
                            @endif
                        </div>
                        <div class="small text-muted font-monospace">{{{ ltrim($key, 'a') }}}</div>
                        @if ($error)
                            <div>
                                @if ($noUpdateSite)
                                    <span class="badge bg-warning">
                                        <span class="fa fa-globe" aria-hidden="true"></span>
                                        @lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATESITE_MISSING')
                                    </span>
                                @elseif ($missingDownloadID)
                                    <span class="badge bg-danger">
                                        <span class="fa fa-key" aria-hidden="true"></span>
                                        @lang('PANOPTICON_SITE_LBL_EXTENSIONS_DOWNLOADKEY_MISSING')
                                    </span>

                                    @if ($user->authorise('panopticon.admin', $this->item->id))
                                        <a href="@route(sprintf('index.php?view=site&task=dlkey&id=%d&extension=%d&%s=1', $this->item->id, $extensionId, $token))"
                                           class="ms-2 btn btn-outline-primary btn-sm" role="button">
                                            <span class="fa fa-pencil-square" aria-hidden="true"></span>
                                            <span class="visually-hidden">@lang('PANOPTICON_BTN_EDIT')</span>
                                        </a>
                                    @endif
                                @endif
                            </div>
                        @elseif (($item->downloadkey?->supported ?? false) && !empty($item->downloadkey?->value ?? '') && $this->container->userManager->getUser()->getPrivilege('panopticon.admin'))
                            <span class="fa fa-key text-muted" ></span>
                            <span class="visually-hidden">Download Key: </span>
                            <code class="download-key" tabindex="0">{{{ $item->downloadkey?->value ?? '' }}}</code>
                            @if ($user->authorise('panopticon.admin', $this->item->id))
                                <a href="@route(sprintf('index.php?view=site&task=dlkey&id=%d&extension=%d&%s=1', $this->item->id, $extensionId, $token))"
                                   class="ms-2 btn btn-outline-primary btn-sm" role="button">
                                    <span class="fa fa-pencil-square" aria-hidden="true"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_BTN_EDIT')</span>
                                </a>
                            @endif
                        @endif
                    </td>
                    <td class="small">
                        <div>
                            @if ($item->authorUrl)
                                <a href="{{{ $item->authorUrl }}}" target="_blank">
                                    {{{ $item->author }}}
                                </a>
                            @else
                                {{{ $item->author }}}
                            @endif
                        </div>
                        @if ($item->authorEmail)
                            <div class="text-muted">
                                {{{ $item->authorEmail }}}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if ($hasUpdate && $error)
                            <strong class="text-danger-emphasis">
                                {{{ $item->version->current }}}
                            </strong>
                            <div class="ps-4 text-body-tertiary">
                                <span class="fa fa-arrow-right small" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANNOTINSTALL_SR')</span>
                                <span class="fw-medium small">
                                    {{{ $item->version->new }}}
                                </span>
                                <span class="fa fa-lock text-danger"></span>
                            </div>
                        @elseif ($hasUpdate)
                            <strong class="text-danger-emphasis">
                                {{{ $item->version->current }}}
                            </strong>
                            <div class="ps-4">
                                <span class="fa fa-arrow-right text-body-tertiary small" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANINSTALL_SR')</span>
                                <span class="fw-medium text-info small">
                                    {{{ $item->version->new }}}
                                </span>
                            </div>

                            {{-- Button to install the update (if not scheduled, or if schedule failed) --}}
                            @if (!in_array($item->extension_id, $scheduledExtensions) && $hasUpdate && !$error && !$willAutoUpdate($key, $currentVersion, $latestVersion))
                                <a class="btn btn-sm btn-outline-primary" role="button"
                                   title="@sprintf('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_SCHEDULE_UPDATE', $this->escape($item->version->new))"
                                   href="@route(sprintf('index.php?view=site&task=scheduleExtensionUpdate&site_id=%d&id=%d&%s=1', $this->item->id, $item->extension_id, $token))">
                                    <span class="fa fa-bolt" aria-hidden="true"></span>
                                    <span class="visually-hidden">@sprintf('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_SCHEDULE_UPDATE', $this->escape($item->version->new))</span>
                                </a>
                            @endif


                        @else
                            {{{ $item->version->current }}}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

    </div>
</div>
