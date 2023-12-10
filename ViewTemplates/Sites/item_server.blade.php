<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

$config     = $this->item->getConfig();
$serverInfo = $config->get('core.serverInfo');
$sameDisk   = ($serverInfo->siteDisk?->mount ?? '') === ($serverInfo->dbDisk?->mount ?? '');
$ioWait     = floatval(trim($serverInfo->cpuUsage?->iowait ?? 0));
$freeCpu    = floatval(trim($serverInfo->cpuUsage?->idle ?? 0));
$freeSite   = !empty($serverInfo->siteDisk->free ?? 0)
    ? 100 * (($serverInfo->siteDisk->free ?? 0) / ($serverInfo->siteDisk->total ?: 1))
    : null;
$freeDb     = !empty($serverInfo->dbDisk->free ?? 0)
    ? 100 * (($serverInfo->dbDisk->free ?? 0) / ($serverInfo->dbDisk->total ?: 1))
    : null;

?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-server" aria-hidden="true"></span>
        <span class="flex-grow-1">
            @lang('PANOPTICON_SITE_LBL_SERVER_HEAD')
        </span>

        <button class="btn btn-success btn-sm ms-2" role="button"
                data-bs-toggle="collapse" href="#cardServerBody"
                aria-expanded="true" aria-controls="cardServerBody"
                data-bs-tooltip="tooltip" data-bs-placement="bottom"
                data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
        >
            <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
        </button>
    </h3>
    <div class="card-body collapse show" id="cardServerBody">

        {{-- OS and CPU summary --}}
        <div class="d-flex flex-column flex-lg-row align-items-center justify-content-lg-between gap-2 mb-2">
            {{-- Operating System --}}
            <div class="d-flex flex-column flex-sm-row fs-5 align-items-center gap-2">
                <div>
                    @if (($serverInfo->os?->family ?? null) === 'Linux')
                        <span class="fab fa-fw fa-linux" aria-hidden="true"></span>
                        <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_OS_LINUX')</span>
                    @elseif (($serverInfo->os?->family ?? null) === 'Windows')
                        <span class="fab fa-fw fa-windows" aria-hidden="true"></span>
                        <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_OS_WINDOWS')</span>
                    @elseif (($serverInfo->os?->family ?? null) === 'BSD')
                        <span class="fab fa-fw fa-freebsd" aria-hidden="true"></span>
                        <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_OS_BSD')</span>
                    @elseif (($serverInfo->os?->family ?? null) === 'macOS')
                        <span class="fab fa-fw fa-apple" aria-hidden="true"></span>
                        <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_OS_MACOS')</span>
                    @else
                        <span class="fa fa-fw fa-circle-question" aria-hidden="true"></span>
                        <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_OS_OTHER')</span>
                    @endif
                </div>
                @if(!empty($serverInfo->os?->os ?? null) || !empty($serverInfo->os?->kernel ?? null))
                    <div>
                        @if(($serverInfo->os?->os ?? null))
                            <span>{{{ $serverInfo->os?->os }}}</span>
                        @else
                            @lang('PANOPTICON_SITE_LBL_SERVER_OS_' . $serverInfo->os->family)
                        @endif
                        @if(($serverInfo->os?->kernel ?? null))
                            <span class="text-body-tertiary">({{{ $serverInfo->os?->kernel }}})</span>
                        @endif
                    </div>
                @elseif(($serverInfo->os?->family ?? null) === 'Windows')
                    <div>
                        @lang('PANOPTICON_SITE_LBL_SERVER_OS_WINDOWS')
                    </div>
                @else
                    <div>
                        @lang('PANOPTICON_SITE_LBL_SERVER_OS_OTHER')
                    </div>
                @endif
            </div>

            {{-- CPU --}}
            @if ($serverInfo->cpuInfo?->cores ?? null)
            <div class="d-flex flex-column flex-md-row align-items-center gap-2">
                <div class="fs-5">
                    <span class="fa fa-fw fa-microchip" aria-hidden="true"
                          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_CPU')"
                    ></span>
                    <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_CPU')</span>
                </div>
                <div>
                    <span class="text-secondary">
                        {{ (int) $serverInfo->cpuInfo?->cores }}
                    </span>
                    <span class="text-body-tertiary">×</span>
                </div>
                <div class="text-secondary">
                    @if ($serverInfo->cpuInfo?->model ?? null)
                        {{{ $serverInfo->cpuInfo->model }}}
                    @else
                        @lang('PANOPTICON_SITE_LBL_SERVER_CPU_UNKNOWN')
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- Uptime, Server Load and CPU Utilisation --}}
        <div class="d-flex flex-column flex-lg-row align-items-center justify-content-lg-between gap-3 mb-2">

            {{-- Uptime --}}
            @if($serverInfo->load?->uptime ?? '')
            <div>
                <span class="fa fa-fw fa-clock" aria-hidden="true"
                      data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_UPTIME')"
                ></span>
                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_UPTIME')</span>
                {{ $this->minutesToHumanReadable($serverInfo->load->uptime) }}
            </div>
            @endif

            {{-- CPU Utilisation --}}
            @if(!empty(trim($serverInfo->cpuUsage?->user ?? '')))
            <div class="d-flex flex-row align-items-center gap-2 flex-grow-1">
                <span class="fa fa-fw fa-gauge" aria-hidden="true"
                      data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_UTILISATION')"
                ></span>
                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_CPU_UTILISATION')</span>

                <div class="progress-stacked w-100">
                    <div class="progress" role="progressbar"
                         aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_USER')"
                         aria-valuenow="{{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->user)) }}}"
                         style="width: {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->user)) }}}%"
                         aria-valuemin="0" aria-valuemax="100"
                         data-bs-toggle="tooltip" data-bs-placement="bottom"
                         data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_USER'): {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->user)) }}}%"
                    >
                        <div class="progress-bar"></div>
                    </div>

                    <div class="progress" role="progressbar"
                         aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_IOWAIT')"
                         aria-valuenow="{{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->iowait)) }}}"
                         style="width: {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->iowait)) }}}%"
                         aria-valuemin="0" aria-valuemax="100"
                         data-bs-toggle="tooltip" data-bs-placement="bottom"
                         data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_IOWAIT'): {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->iowait)) }}}%"
                    >
                        <div class="progress-bar bg-warning"></div>
                    </div>

                    <div class="progress" role="progressbar"
                         aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_SYS')"
                         aria-valuenow="{{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->system)) }}}"
                         style="width: {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->system)) }}}%"
                         aria-valuemin="0" aria-valuemax="100"
                         data-bs-toggle="tooltip" data-bs-placement="bottom"
                         data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_SYS'): {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->system)) }}}%"
                    >
                        <div class="progress-bar bg-danger"></div>
                    </div>

                    <div class="progress" role="progressbar"
                         aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_IDLE')"
                         aria-valuenow="{{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->idle)) }}}"
                         style="width: {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->idle)) }}}%"
                         aria-valuemin="0" aria-valuemax="100"
                         data-bs-toggle="tooltip" data-bs-placement="bottom"
                         data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_CPU_TYPE_IDLE'): {{{ sprintf('%0.2f', floatval($serverInfo->cpuUsage->idle)) }}}%"
                    >
                        <div class="progress-bar bg-body-secondary"></div>
                    </div>

                </div>
            </div>
            @endif

            {{-- Server load --}}
            @if(!empty(trim($serverInfo->load?->load1 ?? '')))
            <div>
                <span class="fa fa-fw fa-gauge" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_LOAD')</span>

                @if(!empty(trim($serverInfo->load?->load1 ?? '')))
                    <span aria-hidden="true" class="ms-2 text-secondary">①</span>
                    {{{ sprintf('%0.2f', floatval($serverInfo->load->load1)) }}}
                @endif

                @if(!empty(trim($serverInfo->load?->load5 ?? '')))
                    <span aria-hidden="true" class="ms-2 text-secondary">⑤</span>
                    {{{ sprintf('%0.2f', floatval($serverInfo->load->load5)) }}}
                @endif

                @if(!empty(trim($serverInfo->load?->load15 ?? '')))
                    <span aria-hidden="true" class="ms-2 text-secondary">⑮</span>
                    {{{ sprintf('%0.2f', floatval($serverInfo->load->load15)) }}}
                @endif
            </div>
            @endif
        </div>

        @if ($ioWait >= 5.0)
            <div class="alert alert-warning my-3">
                <h4 class="h5 alert-heading">
                    <span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"></span>
                    @sprintf('PANOPTICON_SITE_LBL_SERVER_WARN_IOWAIT', $ioWait)
                </h4>
                <div>
                    @lang('PANOPTICON_SITE_LBL_SERVER_WARN_IOWAIT_WHAT')
                    @lang('PANOPTICON_SITE_LBL_SERVER_CALL_YOUR_HOST')
                </div>
            </div>
        @endif

        @if ($freeCpu <= 10.0)
            <div class="alert alert-warning my-3">
                <h4 class="h5 alert-heading">
                    <span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"></span>
                    @sprintf('PANOPTICON_SITE_LBL_SERVER_WARN_CPU', 100 - $freeCpu)
                </h4>
                <div>
                    @lang('PANOPTICON_SITE_LBL_SERVER_WARN_CPU_WHAT')
                    @lang('PANOPTICON_SITE_LBL_SERVER_CALL_YOUR_HOST')
                </div>
            </div>
        @endif

        {{-- Memory information --}}
        @if (($serverInfo?->memory?->total ?? null) !== null)
        <?php
	    $used  = floatval($serverInfo->memory?->used ?? 0);
	    $cache = floatval($serverInfo->memory?->cache ?? 0);
	    $free  = floatval($serverInfo->memory?->free ?? 0);
	    $total = floatval($serverInfo->memory?->total ?? 0) ?: ($used + $cache + $free);

	    $usedPercent  = 100 * ($used / $total);
	    $cachePercent = 100 * ($cache / $total);
	    $freePercent  = 100 * ($free / $total);

		$class = '';

		if ($usedPercent > 70)
        {
            $class = 'bg-warning';
        }

		if ($usedPercent >= 85)
        {
            $class = 'bg-danger';
        }
	    ?>
        <div class="d-flex flex-row align-items-center gap-2 flex-grow-1 mb-2">
            <span class="fa fa-fw fa-memory" aria-hidden="true"
                  data-bs-toggle="tooltip"
                  data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_RAM_UTILISATION')"
            ></span>
            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_SERVER_RAM_UTILISATION')</span>

            <div class="progress-stacked w-100">
                <div class="progress" role="progressbar"
                     aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_RAM_TYPE_USED')"
                     aria-valuenow="{{{ sprintf('%0.2f', $usedPercent) }}}"
                     style="width: {{{ sprintf('%0.2f', floatval($usedPercent)) }}}%"
                     aria-valuemin="0" aria-valuemax="100"
                     data-bs-toggle="tooltip" data-bs-placement="bottom"
                     data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_RAM_TYPE_USED'): {{{ sprintf('%u MiB', $used) }}}"
                >
                    <div class="progress-bar {{ $class }}"></div>
                </div>

                <div class="progress" role="progressbar"
                     aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_RAM_TYPE_CACHE')"
                     aria-valuenow="{{{ sprintf('%0.2f', $cachePercent) }}}"
                     style="width: {{{ sprintf('%0.2f', floatval($cachePercent)) }}}%"
                     aria-valuemin="0" aria-valuemax="100"
                     data-bs-toggle="tooltip" data-bs-placement="bottom"
                     data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_RAM_TYPE_CACHE'): {{{ sprintf('%u MiB', floatval($cache)) }}}"
                >
                    <div class="progress-bar progress-bar-striped bg-info"></div>
                </div>

                <div class="progress" role="progressbar"
                     aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_RAM_TYPE_FREE')"
                     aria-valuenow="{{{ sprintf('%0.2f', $freePercent) }}}"
                     style="width: {{{ sprintf('%0.2f', floatval($freePercent)) }}}%"
                     aria-valuemin="0" aria-valuemax="100"
                     data-bs-toggle="tooltip" data-bs-placement="bottom"
                     data-bs-title="@lang('PANOPTICON_SITE_LBL_SERVER_RAM_TYPE_FREE'): {{{ sprintf('%u MiB', floatval($free)) }}}"
                >
                    <div class="progress-bar bg-body-secondary"></div>
                </div>
            </div>
        </div>
        @endif

        {{-- Disk usage --}}
        <table class="table table-borderless">
            <comment class="visually-hidden">
                @lang('PANOPTICON_SITE_LBL_SERVER_DISK_TABLE_COMMENT')
            </comment>
            <tbody>

            @if (!empty($serverInfo->siteDisk->total ?? '') && !empty($serverInfo->siteDisk->free ?? ''))
            <?php
            $total   = $serverInfo->siteDisk->total;
            $free    = $serverInfo->siteDisk->free;
            $used    = $total - $free;
            $percent = 100 * ($used / $total);
            ?>
            <tr>
                <th scope="row">
                    @if($sameDisk)
                        @lang('PANOPTICON_SITE_LBL_SERVER_DISK_COMMON')
                    @else
                        @lang('PANOPTICON_SITE_LBL_SERVER_DISK_SITE')
                    @endif
                    @if (!empty($serverInfo->siteDisk->mount ?? ''))
                    <br>
                    <span class="fw-light small">
                        @lang('PANOPTICON_SITE_LBL_SERVER_MOUNT_POINT') <code>{{{ $serverInfo->siteDisk->mount }}}</code>
                    </span>
                    @endif
                </th>
                <td class="w-75">
                    <div class="progress" role="progressbar"
                         aria-label="@if($sameDisk) @lang('PANOPTICON_SITE_LBL_SERVER_DISK_COMMON') @else @lang('PANOPTICON_SITE_LBL_SERVER_DISK_SITE') @endif"
                         aria-valuenow="{{{ sprintf('%0.2f', $percent) }}}"
                         aria-valuemin="0"
                         aria-valuemax="100"
                    >
                        <div class="progress-bar" style="width: {{{ sprintf('%0.2f', $percent) }}}%">
                            {{{ sprintf('%0.2f', $percent) }}}%
                        </div>
                    </div>

                    <div class="small text-secondary mt-1">
                        @sprintf('PANOPTICON_SITE_LBL_SERVER_USED', $used, $total)
                        &bull;
                        @sprintf('PANOPTICON_SITE_LBL_SERVER_FREE', $free)
                    </div>
                </td>
            </tr>
            @endif

            @if (!$sameDisk && !empty($serverInfo->dbDisk->total ?? '') && !empty($serverInfo->dbDisk->free ?? ''))
            <?php
            $total   = $serverInfo->dbDisk->total;
            $free    = $serverInfo->dbDisk->free;
            $used    = $total - $free;
            $percent = 100 * ($used / $total);
            ?>
            <tr>
                <th scope="row">
                    @lang('PANOPTICON_SITE_LBL_SERVER_DISK_DB')
                    @if (!empty($serverInfo->dbDisk->mount ?? ''))
                        <br>
                        <span class="fw-light small">
                        @lang('PANOPTICON_SITE_LBL_SERVER_MOUNT_POINT') <code>{{{ $serverInfo->dbDisk->mount }}}</code>
                    </span>
                    @endif
                </th>
                <td class="w-75">
                    <div class="progress" role="progressbar"
                         aria-label="@lang('PANOPTICON_SITE_LBL_SERVER_DISK_SITE')"
                         aria-valuenow="{{{ sprintf('%0.2f', $percent) }}}"
                         aria-valuemin="0"
                         aria-valuemax="100"
                    >
                        <div class="progress-bar" style="width: {{{ sprintf('%0.2f', $percent) }}}%">
                            {{{ sprintf('%0.2f', $percent) }}}%
                        </div>
                    </div>

                    <div class="small text-secondary mt-1">
                        @sprintf('PANOPTICON_SITE_LBL_SERVER_USED', $used, $total)
                        &bull;
                        @sprintf('PANOPTICON_SITE_LBL_SERVER_FREE', $free)
                    </div>
                </td>
            </tr>
            @endif
            </tbody>
        </table>

        @if ($freeSite !== null && $freeSite <= 10.0)
            <div class="alert alert-warning my-3">
                <h4 class="h5 alert-heading">
                    <span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"></span>
                    @if ($sameDisk)
                        @sprintf('PANOPTICON_SITE_LBL_SERVER_WARN_SITE_DISK', 100 - $freeSite)
                    @else
                        @sprintf('PANOPTICON_SITE_LBL_SERVER_WARN_COMMON_DISK', 100 - $freeSite)
                    @endif
                </h4>
                <div>
                    @if ($sameDisk)
                        @lang('PANOPTICON_SITE_LBL_SERVER_WARN_COMMON_DISK_WHAT')
                    @else
                        @lang('PANOPTICON_SITE_LBL_SERVER_WARN_SITE_DISK_WHAT')
                    @endif

                    @lang('PANOPTICON_SITE_LBL_SERVER_CALL_YOUR_HOST')
                </div>
            </div>
        @endif

        @if (!$sameDisk && $freeDb !== null && $freeDb <= 10.0)
            <div class="alert alert-warning my-3">
                <h4 class="h5 alert-heading">
                    <span class="fa fa-fw fa-exclamation-circle" aria-hidden="true"></span>
                    @sprintf('PANOPTICON_SITE_LBL_SERVER_WARN_DB_DISK', 100 - $freeDb)
                </h4>
                <div>
                    @lang('PANOPTICON_SITE_LBL_SERVER_WARN_DB_DISK_WHAT')
                    @lang('PANOPTICON_SITE_LBL_SERVER_CALL_YOUR_HOST')
                </div>
            </div>
        @endif

    </div>
</div>
