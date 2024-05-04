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

$config             = $site->getConfig();
$cmsTypeHuman       = match ($site->cmsType())
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
{{ $cmsTypeHuman }} Core-Updates
==============================================================================

@if($coreUpdates === null)
Keine ausstehenden Core-Updates vorhanden.

Es wird {{ $cmsTypeHuman }} {{ $config->get('core.current.version') }} verwendet, welches die letzte Version darstellt,
die gemäß den Update-Konfigurationsoptionen auf der Site installiert werden kann.
@else
Ein {{ $cmsTypeHuman }} Update vorhanden.

Es wird aktuell {{ $cmsTypeHuman }} {{ $coreUpdates->current }} verwendet, was auf {{ $cmsTypeHuman }}
{{ $coreUpdates->latest }} aktualisiert werden kann.
@endif

@stop

@section('mail_scheduled_summary_email_extension_updates')
{{ ucfirst($extensionNameHuman) }} Updates
==============================================================================

@if(empty($extensionUpdates))
Keine {{{ $extensionNameHuman }}} Updates vorhanden.
@else
@if (count($extensionUpdates) == 1)
Ein ausstehendes Erweiterungs-Update vorhanden.
@else
{{ count($extensionUpdates) }} ausstehende Erweiterungs-Updates vorhanden.

@endif
@foreach($extensionUpdates as $item)
 * @if($site->cmsType() === CMSType::WORDPRESS)@if($item['type'] === 'plugin')[Plugin]@else[Theme]@endif@else#{{ $item['id'] }}@endif “{{{ strip_tags($item['name']) }}}” @if (!empty(trim($item['author_url'] ?? ''))) by {{{ strip_tags($item['author']) }}} @endif – from {{{ $item['current'] }}} to {{{ $item['new'] }}}
@endforeach
@endif

@stop

******************************************************************************
@if ($reportCoreUpdates && $reportExtensionUpdates)
Vorhandener {{{ $cmsTypeHuman }}} und {{{ $extensionNameHuman }}} Update-Bericht für Site #{{ $site->id }} ({{{ $site->name }}})
@elseif ($reportCoreUpdates)
Vorhandener {{{ $cmsTypeHuman }}} Update-Bericht für Site #{{ $site->id }} ({{{ $site->name }}})
@elseif ($reportExtensionUpdates)
Vorhandener {{{ $extensionNameHuman }}} Update-Bericht für Site #{{ $site->id }} ({{{ $site->name }}})
@endif
******************************************************************************

@if ($reportCoreUpdates)@yield('mail_scheduled_summary_email_core_updates')@endif
@if ($reportExtensionUpdates)@yield('mail_scheduled_summary_email_extension_updates')@endif