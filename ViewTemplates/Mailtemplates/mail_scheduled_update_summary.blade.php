<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * @var \Akeeba\Panopticon\View\Mailtemplates\Html $this
 * @var bool                                       $reportCoreUpdates
 * @var object|null                                $coreUpdates
 * @var bool                                       $reportExtensionUpdates
 * @var array|null                                 $extensionUpdates
 * @var \Akeeba\Panopticon\Model\Site              $site
 */

$config             = $site->getConfig();
$cmsType            = $config->get('cmsType', 'joomla');
$cmsTypeHuman       = match ($cmsType)
{
	default => 'Joomla!™',
	'wordpress' => 'WordPress',
};
$extensionNameHuman = match ($cmsType)
{
	default => 'extension',
	'wordpress' => 'plugin and theme',
};
?>

@section('mail_scheduled_summary_email_core_updates')
    <h3>
        {{ $cmsTypeHuman }} core updates
    </h3>
    @if($coreUpdates === null)
        <p>
            There are no pending core updates to report. You are
            using {{ $cmsTypeHuman }} {{ $config->get('core.current.version') }} which is the latest version which can
            be installed on your site as per its update configuration options.
        </p>
    @else
        <p>
            <strong>There is a {{ $cmsTypeHuman }} update available</strong>. You are currently
            using {{ $cmsTypeHuman }} {{ $coreUpdates->current }} which can be updated
            to {{ $cmsTypeHuman }} {{ $coreUpdates->latest }}.
        </p>
    @endif
@stop

@section('mail_scheduled_summary_email_extension_updates')
    <h3>
        {{ ucfirst($extensionNameHuman) }} updates
    </h3>
    @if(empty($extensionUpdates))
        <p>
            There are no pending {{{ $extensionNameHuman }}} updates to report.
        </p>
    @else
        <p>
            @if (count($extensionUpdates) == 1)
                <strong>There is one pending extension update.</strong>
            @else
                <strong>There are {{ count($extensionUpdates) }} pending extension updates.</strong>
            @endif
        </p>
        <ul>
            @foreach($extensionUpdates as $item)
                <li>
                    @if ($cmsType === 'wordpress')
                        @if ($item['type'] === 'plugin')
                            [Plugin]
                        @else
                            [Theme]
                        @endif
                    @else
                        #{{ $item['id'] }}
                    @endif
                    “{{{ strip_tags($item['name']) }}}”
                    @if (!empty(trim($item['author'] ?? '')))
                        by
                        @if (!empty(trim($item['author_url'] ?? '')))
								<?php
								$uri = new \Awf\Uri\Uri(trim(strip_tags($item['author_url'] ?? '')));
								$uri->setScheme($uri->getScheme() ?: 'http://');
								?>
                            <a href="{{{ $uri->toString() }}}">{{{ strip_tags($item['author']) }}}</a>
                        @else
                            {{{ strip_tags($item['author']) }}}
                        @endif
                    @endif
                    – from {{{ $item['current'] }}} to {{{ $item['new'] }}}
                </li>
            @endforeach
        </ul>
    @endif
@stop

<!-- Main-Topic -->
<div class="akemail-main-topic">
    <p>
        @if ($reportCoreUpdates && $reportExtensionUpdates)
            Available {{{ $cmsTypeHuman }}} and {{{ $extensionNameHuman }}} updates report for site #{{ $site->id }}
            ({{{ $site->name }}})
        @elseif ($reportCoreUpdates)
            Available {{{ $cmsTypeHuman }}} Updates report for site #{{ $site->id }} ({{{ $site->name }}})
        @elseif ($reportExtensionUpdates)
            Available {{{ $extensionNameHuman }}} updates report for site #{{ $site->id }} ({{{ $site->name }}})
        @endif
    </p>
</div>

<!-- Message -->
<div class="akemail-message">
    @if ($reportCoreUpdates)
        @yield('mail_scheduled_summary_email_core_updates')
    @endif

    @if ($reportExtensionUpdates)
        @yield('mail_scheduled_summary_email_extension_updates')
    @endif
</div>