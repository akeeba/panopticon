<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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

use Akeeba\Panopticon\Library\Enumerations\CMSType;

$config       = $site->getConfig();
$cmsTypeHuman = match ($site->cmsType())
{
	default => 'Joomla!&reg;',
	CMSType::WORDPRESS => 'WordPress',
};
$extensionNameHuman = match ($site->cmsType())
{
	default => 'extension',
	CMSType::WORDPRESS => 'plugin and theme',
};
?>

@section('mail_scheduled_summary_email_core_updates')
    <h3>
        {{ $cmsTypeHuman }} Core-Updates
    </h3>
    @if($coreUpdates === null)
        <p>
            Keine ausstehenden Core-Updates vorhanden. Es wird
            {{ $cmsTypeHuman }} {{ $config->get('core.current.version') }} verwendet, welches die letzte Version darstellt,
            die gemäß den Update-Konfigurationsoptionen auf der Site installiert werden kann.
        </p>
    @else
        <p>
            <strong>Ein {{ $cmsTypeHuman }} Update vorhanden</strong>. Es wird aktuell
            {{ $cmsTypeHuman }} {{ $coreUpdates->current }} verwendet, was auf
			{{ $cmsTypeHuman }} {{ $coreUpdates->latest }} aktualisiert werden kann.
        </p>
    @endif
@stop

@section('mail_scheduled_summary_email_extension_updates')
    <h3>
        {{ ucfirst($extensionNameHuman) }} Updates
    </h3>
    @if(empty($extensionUpdates))
        <p>
            Keine {{{ $extensionNameHuman }}} Updates vorhanden.
        </p>
    @else
        <p>
            @if (count($extensionUpdates) == 1)
                <strong>Ein ausstehendes Erweiterungs-Update vorhanden.</strong>
            @else
                <strong>{{ count($extensionUpdates) }} ausstehende Erweiterungs-Updates vorhanden.</strong>
            @endif
        </p>
        <ul>
            @foreach($extensionUpdates as $item)
                <li>
                    @if ($site->cmsType() === CMSType::WORDPRESS)
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
            Vorhandener {{{ $cmsTypeHuman }}} und {{{ $extensionNameHuman }}} Update-Bericht für Site #{{ $site->id }}
            ({{{ $site->name }}})
        @elseif ($reportCoreUpdates)
            Vorhandener {{{ $cmsTypeHuman }}} Update-Bericht für Site #{{ $site->id }} ({{{ $site->name }}})
        @elseif ($reportExtensionUpdates)
            Vorhandener {{{ $extensionNameHuman }}} Update-Bericht für Site #{{ $site->id }} ({{{ $site->name }}})
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