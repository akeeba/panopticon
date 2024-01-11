<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * @var \Akeeba\Panopticon\Model\Reports[] $records
 * @var \Akeeba\Panopticon\Model\Sites     $site
 * @var DateTime                           $start
 * @var DateTime                           $end
 */
?>
@section('renderErrorCell')
    <?php
    $this->errorContext = is_object($this->errorContext ?? null) ? (array) $this->errorContext : ($this->errorContext ?? null);
    ?>
    @if (is_array($this->errorContext) && !empty($this->errorContext))
        @if (isset($this->errorContext['value']))
            @if (is_bool($this->errorContext['value']))
                @if($this->errorContext['value'])
                    @lang('AWF_YES')
                @else
                    @lang('AWF_NO')
                @endif
            @elseif(is_scalar($this->errorContext['value']))
                {{{ $this->errorContext['value'] }}}
            @elseif(is_array($this->errorContext['value']))
                <?php $this->errorContext = $this->errorContext['value'] ?>
            @elseif(is_object($this->errorContext['value']))
                <?php $this->errorContext = (array) $this->errorContext['value'] ?>
            @endif
        @endif


        @if (!empty($exception = ($this->errorContext['exception'] ?? null)))
		        <?php $exception = is_array($exception) ? $exception : (array) $exception ?>
            <details>
                <summary>
                    <code>#{{{ $exception['code'] ?? 0 }}}.</code> {{{ $exception['message'] ?? '' }}}
                </summary>
            </details>
        @elseif(is_array($this->errorContext))
            <table>
                <tbody>
                @foreach($this->errorContext as $k => $v)
                    <tr>
                        <th>{{{ $k }}}</th>
                        <td>
                            @if (is_scalar($v))
                                {{{ $v }}}
                            @elseif(is_array($v))
                                {{{ print_r($v, true) }}}
                            @elseif(is_object($v))
                                {{{ print_r((array) $v, true) }}}
                            @else
                                (Not an array or object)
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif
@stop
@repeatable('renderCoreUpdateFound', $item)
    <?php
    $oldVersion = $item->context->get('oldVersion');
    $newVersion = $item->context->get('newVersion');
    ?>
    <div>
        ℹ️ @lang('PANOPTICON_REPORTS_LBL_CORE_UPDATE_FOUND')
    </div>
    @if (!empty($oldVersion) && !empty($newVersion))
        <div style="color: lightsteelblue">
            {{{ $oldVersion }}} → {{{ $newVersion }}}
        </div>
    @endif
@endrepeatable
@repeatable('renderCoreUpdateInstalled', $item)
    <?php
    $success    = $item->context->get('success');
    $oldVersion = $item->context->get('oldVersion');
    $newVersion = $item->context->get('newVersion');
    $startTime  = $item->context->get('start_time');
    $endTime    = $item->context->get('end_time');
    $duration   = (!empty($startTime) && !empty($endTime)) ? ($endTime - $startTime) : null;
    $hasBackup  = $item->context->get('backup_on_update');
    $this->errorContext    = $item->context->get('context');
    ?>
    @if ($success)
        <p>
            ✅ @lang('PANOPTICON_REPORTS_LBL_CORE_UPDATE_INSTALLED_SUCCESS')
        </p>
    @else
        <p>
            ❌ @lang('PANOPTICON_REPORTS_LBL_CORE_UPDATE_INSTALLED_FAILURE')
        </p>
        <div style="border-top: thin dotted mediumvioletred; color: orangered">
            @yield('renderErrorCell')
        </div>
    @endif
    <p>
        @if (!empty($oldVersion) && !empty($newVersion))
            <span style="color: lightsteelblue">
                {{{ $oldVersion }}} → {{{ $newVersion }}}
            </span>
        @endif
        @if ($duration)
            <span style="color: dimgray">
                🕒 {{ $this->timeAgo($startTime, $endTime, autoSuffix: false) }}
            </span>
        @endif
        @if ($hasBackup)
            <span style="color: darkgreen">
                📦
            </span>
        @endif
    </p>
@endrepeatable
@repeatable('renderExtUpdateFound', $item)
    <?php
        $oldVersion = $item->context->get('oldVersion');
        $newVersion = $item->context->get('newVersion');
    ?>
    <div>
        ℹ️ @lang('PANOPTICON_REPORTS_LBL_EXT_UPDATE_FOUND')
    </div>
    <div>
        {{{ $item->context->get('extension.name') }}}
        <span style="font-family: 'Source Code Pro', 'SF Mono', Monaco, Inconsolata, 'Fira Mono', 'Droid Sans Mono', monospace; font-size: small">({{{ $item->context->get('extension.key') }}})</span>
    </div>
    @if (!empty($oldVersion) && !empty($newVersion))
        <div style="color: lightsteelblue">
            {{{ $oldVersion }}} → {{{ $newVersion }}}
        </div>
    @endif
@endrepeatable
@repeatable('renderExtUpdateInstalled', $item)
    <?php
    $success      = $item->context->get('success');
    $oldVersion   = $item->context->get('oldVersion');
    $newVersion   = $item->context->get('newVersion');
    $this->errorContext      = $item->context->get('context');
    ?>
    @if ($success)
        <div>
            ✅ @lang('PANOPTICON_REPORTS_LBL_EXT_UPDATE_INSTALLED_SUCCESS')
        </div>
    @else
        <div>
            ❌
            @lang('PANOPTICON_REPORTS_EXT_CORE_UPDATE_INSTALLED_FAILURE')
        </div>
    @endif

    <div>
        {{{ $item->context->get('extension.name') }}}
        <span style="font-family: 'Source Code Pro', 'SF Mono', Monaco, Inconsolata, 'Fira Mono', 'Droid Sans Mono', monospace; font-size: small">({{{ $item->context->get('extension.key') }}})</span>
    </div>
    @if (!empty($oldVersion) && !empty($newVersion))
        <div style="color: lightsteelblue">
            {{{ $oldVersion }}} → {{{ $newVersion }}}
        </div>
    @endif

    @if (!$success)
        <div style="border-top: thin dotted mediumvioletred; color: orangered">
            @yield('renderErrorCell')
        </div>
    @endif
@endrepeatable
@repeatable('renderBackup', $item)
    <?php
    $status        = $item->context->get('status');
    $archive       = $item->context->get('context.archive');
    $backupId      = $item->context->get('context.backupId');
    $backupRecord  = $item->context->get('context.backupRecord');
    $backupProfile = $item->context->get('backupProfile');
    $this->errorContext       = $item->context->get('context');
    ?>
    <div>
        @if($status)
            ✅ @lang('PANOPTICON_REPORTS_LBL_BACKUP_TAKEN')
        @else
            ❌ @lang('PANOPTICON_REPORTS_LBL_BACKUP_FAILED')
        @endif
    </div>

    @if (!empty($backupProfile) || !empty($backupid))
        <div>
            👤
            @if ($backupProfile)
                <span style="color: darkgray">
                    <small>
                        #{{{ $backupProfile }}}
                    </small>
                </span>
            @endif
            @if ($backupId)
                {{{ $backupId }}}
            @endif
        </div>
    @endif

    @if (boolval($status) && $archive)
        <div>
            📁 {{{ $archive }}}
        </div>
    @elseif(!boolval($status) && !empty($this->errorContext))
        <div style="border-top: thin dotted mediumvioletred; color: orangered">
            @yield('renderErrorCell')
        </div>
    @endif
@endrepeatable
@repeatable('renderFileScanner', $item)
    <?php
    $status  = $item->context->get('status');
    $this->errorContext = $item->context->get('context');
    ?>
    <div>
        @if($status)
            ✅ @lang('PANOPTICON_REPORTS_LBL_FILESCANNER_SUCCESS')
        @else
            ❌ @lang('PANOPTICON_REPORTS_LBL_FILESCANNER_FAILED')
        @endif
    </div>

    @if(!boolval($status) && !empty($this->errorContext))
        <div style="border-top: thin dotted mediumvioletred; color: orangered">
            @yield('renderErrorCell')
        </div>
    @endif
@endrepeatable
@repeatable('renderSiteAction', $item)
    <?php
    $status  = $item->context->get('status');
    $this->errorContext = $item->context->get('context');
    ?>
    <div>
        {{ $status ? '✅' : '❌' }} {{ $item->siteActionAsString() }}
    </div>
    @if(!boolval($status))
        <div style="border-top: thin dotted mediumvioletred; color: orangered">
            @yield('renderErrorCell')
        </div>
    @endif
@endrepeatable
@repeatable('renderSiteUp', $item)
    @lang('PANOPTICON_MAIN_SITES_LBL_UPTIME_UP')
@endrepeatable
@repeatable('renderSiteDown', $item)
    @lang('PANOPTICON_MAIN_SITES_LBL_UPTIME_DOWN')
@endrepeatable
@repeatable('renderMiscAction', $item)
    @lang('PANOPTICON_REPORTS_LBL_MISC')
@endrepeatable

<h3 style="border-bottom: thin solid gray">
    {{{ $site->name }}}
</h3>
<p style="color: darkgray; margin: 6pt 0 0">
    From {{ $start->format($this->getContainer()->language->text('DATE_FORMAT_LC7')) }}
    to {{ $end->format($this->getContainer()->language->text('DATE_FORMAT_LC7')) }}
</p>
<p style="color: darkseagreen; margin: 0 0 12pt">
    {{{ $site->getBaseUrl() ?? '' }}}
</p>

<table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
    <caption>
        @lang('PANOPTICON_REPORTS_LBL_TABLE_CAPTION')
    </caption>
    <thead style="border-bottom: thick solid teal">
    <tr>
        <th scope="col" style="text-align: left" width="20%">
            @lang('PANOPTICON_REPORTS_FIELD_CREATED_ON')
        </th>
        <th scope="col" style="text-align: left" width="20%">
            @lang('PANOPTICON_REPORTS_FIELD_CREATED_BY')
        </th>
        <th scope="col" style="text-align: left">
            @lang('PANOPTICON_REPORTS_FIELD_ACTION')
        </th>
    </tr>
    </thead>
    <tbody>
    @foreach ($records as $item)
        <tr style="border-bottom: thin solid lightgray">
            <td style="padding: 6pt">
                {{{ $this->getContainer()->html->basic->date($item->created_on->format(DATE_ATOM), 'd/n/y H:i:s') }}}
            </td>
            <td style="padding: 6pt">
                @if ($item->created_by->getId() == 0)
                    <strong>⚙️ @lang('PANOPTICON_APP_LBL_SYSTEM_TASK')</strong>
                @else
                    <span style="font-family: 'Source Code Pro', 'SF Mono', Monaco, Inconsolata, 'Fira Mono', 'Droid Sans Mono', monospace">
                        {{ $item->created_by->getUsername() }}
                    </span>
                @endif
            </td>
            <td style="padding: 6pt">
                @if ($item->action->value === 'core_update_found')
                    @yieldRepeatable('renderCoreUpdateFound', $item)
                @elseif ($item->action->value === 'core_update_installed')
                    @yieldRepeatable('renderCoreUpdateInstalled', $item)
                @elseif ($item->action->value === 'ext_update_found')
                    @yieldRepeatable('renderExtUpdateFound', $item)
                @elseif ($item->action->value === 'ext_update_installed')
                    @yieldRepeatable('renderExtUpdateInstalled', $item)
                @elseif ($item->action->value === 'backup')
                    @yieldRepeatable('renderBackup', $item)
                @elseif ($item->action->value === 'filescanner')
                    @yieldRepeatable('renderFileScanner', $item)
                @elseif ($item->action->value === 'site_action')
                    @yieldRepeatable('renderSiteAction', $item)
                @elseif ($item->action->value === 'site_up')
                    @yieldRepeatable('renderSiteUp', $item)
                @elseif ($item->action->value === 'site_down')
                    @yieldRepeatable('renderSiteDown', $item)
                @else
                    @yieldRepeatable('renderMiscAction', $item)
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>