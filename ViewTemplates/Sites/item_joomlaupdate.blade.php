<?php
/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Database\Driver;
use Awf\Registry\Registry;
use Awf\Text\Text;

$config = ($this->item->config instanceof Registry) ? $this->item->config : (new Registry($this->item->config));

$lastUpdateTimestamp = function () use ($config): string {
    $timestamp = $config->get('core.lastUpdateTimestamp');

    return $timestamp ? \Awf\Html\Html::date('@' . $timestamp, Text::_('DATE_FORMAT_LC7')) : '(never)';
};

$getJoomlaUpdateTask = function () use ($config): ?object {
    /** @var Driver $db */
    $db = $this->container->db;
    $query = $db->getQuery(true)
        ->select([
            $db->quoteName('enabled'),
            $db->quoteName('last_exit_code'),
            $db->quoteName('last_execution'),
            $db->quoteName('next_execution'),
            $db->quoteName('storage'),
        ])
        ->from($db->quoteName('#__tasks'))
        ->where([
            $db->quoteName('site_id') . ' = ' . (int)$this->item->id,
            $db->quoteName('type') . ' = ' . $db->quote('joomlaupdate'),
        ]);

    $record = $db->setQuery($query)->loadObject() ?: null;

    if (is_object($record))
    {
        $record->storage = new Awf\Registry\Registry($record?->storage ?: '{}');
    }

    return $record;
};

?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fab fa-joomla d-none d-md-inline" aria-hidden="true"></span>
        <span class="flex-grow-1">Joomla!&trade; Update</span>
        <a type="button" class="btn btn-outline-secondary btn-sm" role="button"
           href="@route(sprintf('index.php?view=site&task=refreshSiteInformation&id=%d', $this->item->id))"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="Reload Joomla!&trade; Update information"
        >
            <span class="fa fa-refresh" aria-hidden="true"></span>
            <span class="visually-hidden">
                Reload Joomla!&trade; Update information
            </span>
        </a>
    </h3>
    <div class="card-body">
        <p class="small text-body-tertiary">
            <strong>
                Last checked:
            </strong>
            {{-- TODO Relative timestamp? --}}
            {{ $lastUpdateTimestamp() }}
        </p>

        @if(!$config->get('core.extensionAvailable', true))
            <div class="alert alert-danger">
                <h4 class="alert-heading h6">
                    <span class="fa fa-xmark-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')
                </h4>
                The Joomla! extension record is missing from your site. Joomla! cannot detect whether updates to itself
                are available.

                Try asking for help on the <a href="https://forum.joomla.org" target="_blank">Joomla! Forum</a>.
            </div>
        @elseif(!$config->get('core.updateSiteAvailable', true))
            <div class="alert alert-danger">
                <h4 class="alert-heading h6">
                    <span class="fa fa-xmark-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')
                </h4>
                The Joomla! Update Site record is missing from your site. Joomla! cannot detect whether updates to
                itself are available.
                @if(version_compare($config->get('core.current.version', '4.0.0'), '4.0.0', 'ge'))
                    Go to your site's administrator backend, System, Update, Update Sites, and click on the Rebuild
                    button.
                @elseif(version_compare($config->get('core.current.version', '4.0.0'), '3.6.0', 'ge'))
                    Go to your site's administrator backend, Extensions, Manage, Update Sites, and click on the Rebuild
                    button.
                @else
                    Try asking for help on the <a href="https://forum.joomla.org" target="_blank">Joomla! Forum</a>.
                @endif
            </div>
        @elseif ($config->get('core.canUpgrade', false))
            <div class="alert alert-warning">
                <h4 class="alert alert-heading h5 p-0">
                    <span class="fab fa-joomla d-none d-md-inline" aria-hidden="true"></span>
                    Joomla! {{ $config->get('core.latest.version') }} is available
                </h4>
                <p class="mb-1">
                    Your site is currently using Joomla! {{ $config->get('core.current.version') }}.
                </p>
                    <?php
                    $versionCurrent = Version::create($config->get('core.current.version'));
                    $versionLatest = Version::create($config->get('core.latest.version'));
                    ?>
                {{-- Is this a major, minor, or patch update? --}}
                @if ($versionCurrent->versionFamily() === $versionLatest->versionFamily())
                    <p class="text-success-emphasis my-1">
                        <span class="fa fa-check-circle" aria-hidden="true"></span>
                        This is a patch (point) update which is most always safe.
                    </p>
                @elseif ($versionLatest->major() === $versionLatest->major())
                    <p class="text-warning-emphasis fw-medium my-1">
                        <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                        This is a minor (version family) update which may affect your template overrides, templates, and
                        some third party extensions.
                    </p>
                    <p class="text-warning-emphasis my-1">
                        It is a good idea to take a backup of your site, and test this update on a copy of your site
                        first.
                    </p>
                @else
                    <p class="text-danger-emphasis fw-bold my-1">
                        <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
                        This is a major version update which will most likely affect your template overrides, templates,
                        and possibly some third party extensions.
                    </p>
                    <p class="text-danger-emphasis my-1">
                        It is very strongly recommended that you take a backup of your site, and test this update on a
                        copy of your site first.
                    </p>
                @endif
            </div>
        @else
            <div class="alert alert-success">
                <h4 class="alert alert-heading h5 p-0 m-0">
                    Joomla! {{ $config->get('core.current.version') }} is up-to-date
                </h4>
                {{-- Is there a new version available, which cannot be installed? --}}
                @if (version_compare($config->get('core.latest.version'), $config->get('core.current.version'), 'lt'))
                    <hr/>
                    <p class="my-2 text-warning-emphasis fw-semibold">
                        The newer Joomla! version {{ $config->get('core.latest.version') }} is available but cannot be installed because of your site preferences.
                    </p>
                    <p class="text-muted">
                        Go to your site's administrator backend, System, Update, Joomla, click on Options, and change the update channel to Joomla Next. Please check Joomla's requirements, especially the database version and PHP version requirements. Note that your site is currently using PHP {{ $config->get('core.php') }}.
                    </p>
                @endif
            </div>
        @endif

        @if ($config->get('core.canUpgrade', false))
            {{-- Is it scheduled? --}}
                <?php
                $joomlaUpdateTask = $getJoomlaUpdateTask();
                $showScheduleButton = true;
                ?>

            @if ($config->get('core.lastAutoUpdateVersion') != $config->get('core.latest.version') || $joomlaUpdateTask === null)
                {{-- Not scheduled --}}
                <p>
                    The update is <span class="text-danger fw-semibold">not scheduled</span> to run automatically. You
                    can run the update manually on your site, or click on the button below.
                </p>
            @elseif ($joomlaUpdateTask->enabled && $joomlaUpdateTask->last_exit_code === Status::OK->value)
                {{-- Pretend it's not scheduled (database tomfoolery abound?) --}}
                <p>
                    The update is <span class="text-danger fw-semibold">not scheduled</span> to run automatically. You
                    can run the update manually on your site, or click on the button below.
                </p>
            @elseif ($joomlaUpdateTask->enabled && $joomlaUpdateTask->last_exit_code === Status::INITIAL_SCHEDULE->value)
                {{-- Scheduled, will run --}}
                <p>
                    @if ($joomlaUpdateTask?->next_execution)
                        The update is <span class="fw-semibold text-success">scheduled</span> to run automatically
                        after {{ \Awf\Html\Html::date($joomlaUpdateTask->next_execution, Text::_('DATE_FORMAT_LC7')) }}
                    @else
                        The update is <span class="fw-semibold text-success">scheduled</span> to run automatically as
                        soon as possible.
                    @endif
                </p>

                    <?php $showScheduleButton = false; ?>
            @elseif ($joomlaUpdateTask->enabled && in_array($joomlaUpdateTask->last_exit_code, [Status::WILL_RESUME->value, Status::RUNNING->value]))
                {{-- Scheduled, running --}}
                <p>
                    The update is <span class="fw-semibold text-success">currently taking place</span> automatically.
                </p>

                    <?php $showScheduleButton = false; ?>
            @else
                {{-- Task error condition --}}
                    <?php $status = Status::tryFrom($joomlaUpdateTask->last_exit_code) ?? Status::NO_ROUTINE ?>
                <p class="text-warning-emphasis">
                    The updated was scheduled to run automatically but ran into an error:
                    {{ $status->forHumans() }}
                </p>
                @if ($status->value === Status::EXCEPTION->value)
                    <p>
                        The error reported was:
                    </p>
                    <p class="text-dark">
                        {{{ $storage->get('error') }}}
                    </p>
                    @if (defined('AKEEBADEBUG') && AKEEBADEBUG)
                        <p>Error trace (for debugging):</p>
                        <pre>
                            {{{ $storage->get('trace') }}}
                        </pre>
                    @endif
                @endif
            @endif

            @if ($showScheduleButton)
                <a href="@route(sprintf('index.php?view=site&task=scheduleJoomlaUpdate&id=%d', $this->item->id))"
                   class="btn btn-outline-warning" role="button">
                    <span class="fa fa-clock" aria-hidden="true"></span>
                    Schedule Automatic Update to Joomla! {{ $config->get('core.latest.version') }}
                </a>
            @endif
        @endif

        @if (!$config->get('core.canUpgrade', false))
            <hr class="mt-4" />
            <p class="text-info">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                Site woes? Try refreshing the Joomla! core files.
            </p>
            <p>
                <a href="@route(sprintf('index.php?view=site&task=scheduleJoomlaUpdate&id=%d', $this->item->id))"
                   class="btn btn-outline-secondary" role="button">
                    <span class="fa fa-clock" aria-hidden="true"></span>
                    Schedule Joomla! {{ $config->get('core.latest.version') }} Files Refresh
                </a>
            </p>
            <p class="small text-muted">
                Refreshing the Joomla! core files is <em>always safe</em>. Your site contents will not be changed. Use this to undo changes to the Joomla! core files (“core hacks”), or if you suspect that your site does not work correctly because of a failed update.
            </p>
        @endif
    </div>
</div>
