<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */

?>
@extends('Sites/item_extensions')

{{-- Override the `extUpdateExtensionIcon` repeatable of the source Blade template --}}
@repeatableOverride('extUpdateExtensionIcon', $item)
@if ($item->type === 'plugin')
    <span class="fa fa-plug fa-fw" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-placement="right"
          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_WP_PLUGIN')"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_WP_PLUGIN')</span>
@elseif ($item->type === 'template')
    <span class="fa fa-paint-brush fa-fw" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-placement="right"
          data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_WP_THEME')"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_WP_THEME')</span>
@endif
@endrepeatable
