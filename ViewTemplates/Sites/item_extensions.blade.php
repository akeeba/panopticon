<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;

$token                = $this->container->session->getCsrfToken()->getValue();
$extensionsUpdateTask = $this->item->getExtensionsUpdateTask();
$scheduledExtensions  = $this->item->getExtensionsScheduledForUpdate();
$lastUpdateTimestamp  = $this->siteConfig->get('extensions.lastAttempt')
    ? $this->timeAgo($this->siteConfig->get('extensions.lastAttempt'))
    : $this->getLanguage()->text('PANOPTICON_LBL_NEVER');
$extensionsQuickInfo = $this->item->getExtensionsQuickInfo($this->extensions);
$shouldCollapse      = false && $extensionsQuickInfo->update == 0 && $extensionsQuickInfo->site == 0
                       && $extensionsQuickInfo->key == 0;
$lastError           = trim($this->siteConfig->get('extensions.lastErrorMessage') ?? '');
$hasError            = !empty($lastError);
?>

@section('extUpdateBadgeHasUpdates')
    @if ($extensionsQuickInfo->update > 0)
        <sup>
                    <span class="badge text-bg-warning"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATES_N', $extensionsQuickInfo->update)"
                    >
                        <span class="fa fa-box-open" aria-hidden="true"></span>
                        <span aria-hidden="true">{{{ $extensionsQuickInfo->update  }}}</span>
                        <span class="visually-hidden">@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATES_N', $extensionsQuickInfo->update)</span>
                    </span>
        </sup>
    @endif
@stop

@section('extUpdateBadgeHasMissingSites')
    @if ($extensionsQuickInfo->site > 0)
        <sup>
                    <span class="badge text-bg-warning"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATESITES_N', $extensionsQuickInfo->site)"
                    >
                        <span class="fa fa-globe" aria-hidden="true"></span>
                        <span aria-hidden="true">{{{ $extensionsQuickInfo->site }}}</span>
                        <span class="visually-hidden">@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_UPDATESITES_N', $extensionsQuickInfo->site)</span>
                    </span>
        </sup>
    @endif
@stop

@section('extUpdateBadgeHasMissingKeys')
    @if ($extensionsQuickInfo->key > 0)
        <sup>
                    <span class="badge bg-danger"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_NOKEY_N', $extensionsQuickInfo->key)"
                    >
                        <span class="fa fa-key" aria-hidden="true"></span>
                        <span aria-hidden="true">{{{ $extensionsQuickInfo->key }}}</span>
                        <span class="visually-hidden">@plural('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD_NOKEY_N', $extensionsQuickInfo->key)</span>
                    </span>
        </sup>
    @endif
@stop

@section('extUpdateErrorInfoButton')
    @if ($lastError)
            <?php $extensionsLastErrorModalID = 'exlem-' . md5(random_bytes(120)); ?>
        <div class="btn btn-danger btn-sm px-1 py-0" aria-hidden="true"
             data-bs-toggle="modal" data-bs-target="#{{ $extensionsLastErrorModalID }}"
        >
                    <span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_ERROR_EXTENSIONS')"
                          data-bs-content="{{{ $lastError }}}"></span>
        </div>

        <div class="modal fade" id="{{ $extensionsLastErrorModalID }}"
             tabindex="-1" aria-labelledby="{{ $extensionsLastErrorModalID }}_label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5"
                            id="{{ $extensionsLastErrorModalID }}_label">
                            @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_EXTENSIONS')
                        </h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="@lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-break">
                            {{{ $lastError }}}
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            @lang('PANOPTICON_APP_LBL_MESSAGE_CLOSE')
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <span class="visually-hidden">
                    @lang('PANOPTICON_MAIN_SITES_LBL_ERROR_EXTENSIONS') {{{ $lastError }}}
                </span>
    @endif
@stop

@section('extUpdateReloadButton')
    <a class="btn btn-outline-secondary btn-sm" role="button"
       href="@route(sprintf('index.php?view=site&task=refreshExtensionsInformation&id=%d&%s=1', $this->item->id, $token))"
       data-bs-toggle="tooltip" data-bs-placement="bottom"
       data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATE_INFO')"
    >
        <span class="fa fa-refresh" aria-hidden="true"></span>
        <span class="visually-hidden">
                @lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATE_INFO')
            </span>
    </a>
@stop

@section('extUpdateShowToggleButton')
    <button class="btn btn-success btn-sm ms-2" role="button"
            data-bs-toggle="collapse" href="#cardExtensionsBody"
            aria-expanded="{{ $shouldCollapse ? 'false' : 'true' }}" aria-controls="cardExtensionsBody"
            data-bs-tooltip="tooltip" data-bs-placement="bottom"
            data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
    >
        <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
        <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
    </button>
@stop

@section('extUpdateFilters')
    <div class="mb-3 mx-1 border rounded-2 border-secondary bg-light-subtle">
        <div class=" p-2 d-flex flex-row gap-3 align-items-baseline justify-content-center">
            <strong>@lang('PANOPTICON_SITE_LBL_EXTENSIONS_FILTERS')</strong>

            @foreach($this->extensionFilters as $filterName => $icon)
                <button type="button"
                        class="btn btn-outline-secondary extensionFilter"
                        data-bs-toggle="button"
                        data-ext-filter="{{ $filterName }}"
                        data-toggle-tooltip="tooltip"
                        data-bs-title="{{{ str_replace('"', '\'', $this->getLanguage()->text('PANOPTICON_SITE_LBL_EXTENSIONS_' . str_replace('-', '_', $filterName))) }}}"
                >
                    <span class="fa {{ $icon }}" aria-hidden="true"></span>
                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_' . str_replace('-', '_', $filterName))</span>
                </button>

            @endforeach
        </div>

        <div class="mt-0 mb-2 px-5 py-3 d-flex flex-column align-items-center justify-content-center gap-2">
            <div class="input-group">
                <span class="input-group-text">
                    <label for="extensions-filter-search" class="visually-hidden">
                        @lang('PANOPTICON_LBL_FORM_FILTER_EXTENSIONS')
                    </label>
                    <span class="fa fa-fw fa-search" aria-hidden="true"></span>
                </span>
                <input type="search" name="extensions-filter-search" id="extensions-filter-search"
                       class="form-control form-control-lg"
                >
                <button type="button" class="btn btn-outline-secondary"
                        id="extensions-filter-search-button">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </button>
            </div>
        </div>
    </div>
@stop

@section('extUpdateScheduleInfo')
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
            @if ($this->cronStuckTime !== null && $extensionsUpdateTask->last_execution < $this->cronStuckTime)
                <div class="alert alert-warning">
                    <h4 class="h5 alert-heading">
                        @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_MAYBE_STUCK')
                    </h4>
                    <div>
                        @sprintf('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_MAYBE_STUCK_HELP', $this->getContainer()->html->basic->date($extensionsUpdateTask->last_execution, $this->getLanguage()->text('DATE_FORMAT_LC7')))
                    </div>
                    <div class="d-flex flex-row align-items-center gap-4 mt-3">
                        <a href="@route(sprintf('index.php?view=site&task=resetExtensionUpdate&resetqueue=0&id=%d&%s=1', $this->item->id, $token))"
                           class="btn btn-success" role="button">
                            <span class="fa fa-refresh" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_RESCHEDULE')
                        </a>
                        <a href="@route(sprintf('index.php?view=site&task=resetExtensionUpdate&resetqueue=1&id=%d&%s=1', $this->item->id, $token))"
                           class="btn btn-outline-danger btn-sm" role="button">
                            <span class="fa fa-fw fa-xmark-circle" aria-hidden="true"></span>
                            @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_CANCEL')
                        </a>
                    </div>
                </div>

            @else
                <div class="alert alert-info">
                    <div class="text-center fs-5">
                        <span class="fa fa-play" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_RUNNING')
                    </div>
                </div>
            @endif
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
        {{-- Show the Reset Extension Updates button if the task does not exist --}}
    @elseif (count($scheduledExtensions))
        <div class="alert alert-warning">
            <h4 class="h5 alert-heading">
                @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_STUCK')
            </h4>
            <div>
                @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_STUCK_HELP')
            </div>
            <div class="d-flex flex-row align-items-center gap-4 mt-3">
                <a href="@route(sprintf('index.php?view=site&task=resetExtensionUpdate&resetqueue=0&id=%d&%s=1', $this->item->id, $token))"
                   class="btn btn-success" role="button">
                    <span class="fa fa-refresh" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_RESCHEDULE')
                </a>
                <a href="@route(sprintf('index.php?view=site&task=resetExtensionUpdate&resetqueue=1&id=%d&%s=1', $this->item->id, $token))"
                   class="btn btn-outline-danger btn-sm" role="button">
                    <span class="fa fa-fw fa-xmark-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITE_LBL_EXTENSION_UPDATE_CANCEL')
                </a>
            </div>
        </div>
    @endif
@stop

@repeatable('extUpdateExtensionIcon', $item)
    @if ($item->type === 'component')
        <span class="fa fa-puzzle-piece fa-fw" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="right"
              data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')</span>
    @elseif ($item->type === 'file')
        <span class="fa fa-file-alt fa-fw" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="right"
              data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')</span>
    @elseif ($item->type === 'library')
        <span class="fa fa-book fa-fw" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="right"
              data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')</span>
    @elseif ($item->type === 'package')
        <span class="fa fa-boxes-packing fa-fw" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="right"
              data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')</span>
    @elseif ($item->type === 'plugin')
        <span class="fa fa-plug fa-fw" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="right"
              data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')</span>
    @elseif ($item->type === 'module')
        <span class="fa fa-cube fa-fw" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="right"
              data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')</span>
    @elseif ($item->type === 'template')
        <span class="fa fa-paint-brush fa-fw" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-placement="right"
              data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')"></span>
        <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')</span>
    @endif
@endrepeatable



<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-cubes" aria-hidden="true"></span>
        <span class="flex-grow-1">
            @lang('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD')
            @yield('extUpdateBadgeHasUpdates')
            @yield('extUpdateBadgeHasMissingSites')
            @yield('extUpdateBadgeHasMissingKeys')
        </span>

        @yield('extUpdateErrorInfoButton')
        @yield('extUpdateReloadButton')
        @yield('extUpdateShowToggleButton')
    </h3>
    <div class="card-body collapse {{ $shouldCollapse ? '' : ' show' }}" id="cardExtensionsBody">

        <p class="small text-body-tertiary">
            <strong>
                @lang('PANOPTICON_SITE_LBL_EXTENSIONS_LAST_CHECKED')
            </strong>
            {{ $lastUpdateTimestamp }}
        </p>

        @yield('extUpdateFilters')
        @yield('extUpdateScheduleInfo')

        <table class="table table-striped table-responsive is-mobile-stack">
            <thead class="table-dark">
            <tr>
                <th>
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_NAME')
                </th>
                <th>
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_AUTHOR')
                </th>
                <th>
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_VERSION')
                </th>
            </tr>
            </thead>
            <tbody>
            @foreach($this->extensions as $extensionId => $item)
                    <?php
                    $key = $this->getModel('Sysconfig')
                                ->getExtensionShortname(
                                    $item->type, $item->element, $item->folder, $item->client_id
                                );

                    // Hide core extensions which are stupidly only ever marked as top-level extensions on core update.
                    if (empty($key) || $this->getModel('Sysconfig')->isExcludedShortname($key))
                    {
                        continue;
                    }

                    $currentVersion    = $item->version?->current;
                    $latestVersion     = $item->version?->new;
                    $noUpdateSite      = !($item->hasUpdateSites ?? false);
                    $missingDownloadID = ($item->downloadkey?->supported ?? false)
                        && !($item->downloadkey?->valid ?? false);
                    $naughtyUpdates    = $item->naughtyUpdates === 'parent';
                    $error             = $noUpdateSite || $missingDownloadID || $naughtyUpdates;
                    $hasUpdate         = !empty($currentVersion) && !empty($latestVersion)
                        && ($currentVersion != $latestVersion)
                        && version_compare($currentVersion, $latestVersion, 'lt');

                    $cssClasses = 'extension-row';
                    $cssClasses .= $noUpdateSite ? ' filter-updatesite' : '';
                    $cssClasses .= $missingDownloadID ? ' filter-dlid' : '';
                    $cssClasses .= $naughtyUpdates ? ' filter-naughty' : '';
                    $cssClasses .= ($hasUpdate && !$noUpdateSite) ? ' filter-update' : ' filter-noupdate';
                    $cssClasses .= $hasUpdate && (in_array($item->extension_id, $scheduledExtensions) || (!$error && $this->willExtensionAutoUpdate($item, $this->item))) ? ' filter-scheduled' :
                        ($hasUpdate ? ' filter-unscheduled' : '');
                    ?>
                <tr class="{{ $cssClasses }}">
                    <td data-label="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NAME')">
                        <div>
                            <span class="text-body-tertiary">
                                @yieldRepeatable('extUpdateExtensionIcon', $item)
                            </span>

                            @if ($error)
                                <span class="text-danger fw-medium extensions-filterable-name">
                                    {{{ strip_tags($item->name) }}}
                                </span>
                            @elseif ($hasUpdate)
                                <span class="text-warning-emphasis fw-bold extensions-filterable-name">
                                    {{{ strip_tags($item->name) }}}
                                </span>
                            @else
                                <span class="extensions-filterable-name">
                                    {{{ strip_tags($item->name) }}}
                                </span>
                            @endif

                            @if (in_array($item->extension_id, $scheduledExtensions))
                                <span class="badge bg-success">
                                    <span class="fa fa-hourglass-half" aria-hidden="true"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_SCHEDULED_UPDATE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_SCHEDULED_UPDATE')</span>
                                </span>
                            @elseif ($hasUpdate && !$error && $this->willExtensionAutoUpdate($item, $this->item))
                                <span class="fa fa-magic-wand-sparkles text-success ms-2" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="top"
                                      data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')</span>
                            @elseif ($hasUpdate && $error && $this->willExtensionAutoUpdate($item, $this->item))
                                <span class="fa fa-magic text-danger ms-2" aria-hidden="true"
                                      data-bs-toggle="tooltip" data-bs-placement="top"
                                      data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')</span>
                            @endif
                        </div>
                        <div class="small text-muted font-monospace extensions-filterable-key">{{{ ltrim($key, 'a') }}}</div>
                        @if ($error)
                            <div>
                                @if ($naughtyUpdates)
                                <a href="https://github.com/akeeba/panopticon/wiki/Extension-With-Problematic-Updates" target="_blank">
                                    <span class="badge bg-danger">
                                        <span class="fa fa-bug" aria-hidden="true"
                                              data-bs-toggle="tooltip" data-bs-placement="right"
                                              data-bs-title="@lang('PANOPTICON_SITES_LBL_NAUGHTY_UPDATES')"
                                        ></span>
                                        <span class="visually-hidden">@lang('PANOPTICON_SITES_LBL_NAUGHTY_UPDATES')</span>
                                    </span>
                                </a>
                                @endif

                                @if ($noUpdateSite)
                                    <a href="https://github.com/akeeba/panopticon/wiki/Extensions-Without-Update-Sites" target="_blank">
                                        <span class="badge text-bg-warning">
                                            <span class="fa fa-globe" aria-hidden="true"></span>
                                            @lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATESITE_MISSING')
                                        </span>
                                    </a>
                                @elseif ($missingDownloadID)
                                    <span class="badge bg-danger">
                                        <span class="fa fa-key" aria-hidden="true"></span>
                                        @lang('PANOPTICON_SITE_LBL_EXTENSIONS_DOWNLOADKEY_MISSING')
                                    </span>

                                    @if ($this->canEdit)
                                        <a href="@route(sprintf('index.php?view=site&task=dlkey&id=%d&extension=%d&%s=1', $this->item->id, $extensionId, $token))"
                                           class="ms-2 btn btn-outline-primary btn-sm" role="button">
                                            <span class="fa fa-pencil-square" aria-hidden="true"></span>
                                            <span class="visually-hidden">@lang('PANOPTICON_BTN_EDIT')</span>
                                        </a>
                                    @endif
                                @endif
                            </div>
                        @elseif (($item->downloadkey?->supported ?? false) && !empty($item->downloadkey?->value ?? '') && $this->container->userManager->getUser()->getPrivilege('panopticon.admin'))
                            <span class="fa fa-key text-muted" aria-hidden="true"></span>
                            <span class="visually-hidden">Download Key: </span>
                            <code class="download-key" tabindex="0">{{{ $item->downloadkey?->value ?? '' }}}</code>
                            @if ($this->canEdit)
                                <a href="@route(sprintf('index.php?view=site&task=dlkey&id=%d&extension=%d&%s=1', $this->item->id, $extensionId, $token))"
                                   class="ms-2 btn btn-outline-primary btn-sm" role="button">
                                    <span class="fa fa-pencil-square" aria-hidden="true"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_BTN_EDIT')</span>
                                </a>
                            @endif
                        @endif
                    </td>
                    <td data-label="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_AUTHOR')" class="small">
                        <div class="extensions-filterable-author">
                            @if ($item->authorUrl)
                                <a href="{{ (str_starts_with($item->authorUrl, 'http://') || str_starts_with($item->authorUrl, 'https://') || str_starts_with($item->authorUrl, '//')) ? '' : '//' }}{{{ $item->authorUrl }}}" target="_blank">
                                    {{{ strip_tags($item->author) }}}
                                </a>
                            @else
                                {{{ strip_tags($item->author) }}}
                            @endif
                        </div>
                        @if ($item->authorEmail)
                            <div class="text-muted">
                                {{{ strip_tags($item->authorEmail) }}}
                            </div>
                        @endif
                    </td>
                    <td data-label="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_VERSION')">
                        @if ($hasUpdate && $error)
                            <strong class="text-danger-emphasis">
                                {{{ $item->version->current }}}
                            </strong>
                            <span class="text-body-tertiary">
                                <span class="fa fa-arrow-right small" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANNOTINSTALL_SR')</span>
                                <span class="fw-medium small">
                                    {{{ $item->version->new }}}
                                </span>
                                <span class="fa fa-lock text-danger" aria-hidden="true"></span>
                            </span>
                        @elseif ($hasUpdate)
                            <strong class="text-danger-emphasis">
                                {{{ $item->version->current }}}
                            </strong>
                            <span>
                                <span class="fa fa-arrow-right text-body-tertiary small" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANINSTALL_SR')</span>
                                <span class="fw-medium text-info small">
                                    {{{ $item->version->new }}}
                                </span>
                            </span>

                            {{-- Button to install the update (if not scheduled, or if schedule failed) --}}
                            @if (!in_array($item->extension_id, $scheduledExtensions) && $hasUpdate && !$error && !$this->willExtensionAutoUpdate($item, $this->item))
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
