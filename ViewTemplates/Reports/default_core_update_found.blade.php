<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var \Akeeba\Panopticon\Model\Reports     $item
 */

$oldVersion = $item->context->get('oldVersion');
$newVersion = $item->context->get('newVersion');
?>
<div>
    <span class="fa fa-fw fa-info-circle text-info" aria-hidden="true"></span>
    @lang('PANOPTICON_REPORTS_LBL_CORE_UPDATE_FOUND')
</div>
@if (!empty($oldVersion) && !empty($newVersion))
<div class="text-info">
    {{{ $oldVersion }}}
    <span class="fa fa-arrow-right" aria-hidden="true"></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_CAN_BE_UPGRADED_SHORT')</span>
    {{{ $newVersion }}}
</div>
@endif