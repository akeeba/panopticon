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

use Akeeba\Panopticon\Library\Enumerations\CMSType;

$config             = $site->getConfig();
$cmsTypeHuman       = match ($site->cmsType())
{
	default => 'Joomla!™',
	CMSType::WORDPRESS => 'WordPress',
};
$extensionNameHuman = match ($site->cmsType())
{
	default => 'extension',
	CMSType::WORDPRESS => 'plugin and theme',
};
?>

@section('mail_scheduled_summary_email_core_updates')
{{ $cmsTypeHuman }} core updates
==============================================================================

@if($coreUpdates === null)
There are no pending core updates to report.

You are using {{ $cmsTypeHuman }} {{ $config->get('core.current.version') }} which is the latest version which can be
installed on your site as per its update configuration options.
@else
There is a {{ $cmsTypeHuman }} update available.

You are currently using {{ $cmsTypeHuman }} {{ $coreUpdates->current }} which can be updated to {{ $cmsTypeHuman }}
{{ $coreUpdates->latest }}.
@endif

@stop

@section('mail_scheduled_summary_email_extension_updates')
{{ ucfirst($extensionNameHuman) }} updates
==============================================================================

@if(empty($extensionUpdates))
There are no pending {{{ $extensionNameHuman }}} updates to report.
@else
@if (count($extensionUpdates) == 1)
There is one pending extension update.
@else
There are {{ count($extensionUpdates) }} pending extension updates.

@endif
@foreach($extensionUpdates as $item)
 * @if($site->cmsType() === CMSType::WORDPRESS)@if($item['type'] === 'plugin')[Plugin]@else[Theme]@endif@else#{{ $item['id'] }}@endif “{{{ strip_tags($item['name']) }}}” @if (!empty(trim($item['author_url'] ?? ''))) by {{{ strip_tags($item['author']) }}} @endif – from {{{ $item['current'] }}} to {{{ $item['new'] }}}
@endforeach
@endif

@stop

******************************************************************************
@if ($reportCoreUpdates && $reportExtensionUpdates)
Available {{{ $cmsTypeHuman }}} and {{{ $extensionNameHuman }}} updates report for site #{{ $site->id }} ({{{ $site->name }}})
@elseif ($reportCoreUpdates)
Available {{{ $cmsTypeHuman }}} Updates report for site #{{ $site->id }} ({{{ $site->name }}})
@elseif ($reportExtensionUpdates)
Available {{{ $extensionNameHuman }}} updates report for site #{{ $site->id }} ({{{ $site->name }}})
@endif
******************************************************************************

@if ($reportCoreUpdates)@yield('mail_scheduled_summary_email_core_updates')@endif
@if ($reportExtensionUpdates)@yield('mail_scheduled_summary_email_extension_updates')@endif