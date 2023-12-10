<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Sites\Html $this */
?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-note-sticky" aria-hidden="true"></span>
        <span class="flex-grow-1">
            @lang('PANOPTICON_SITE_LBL_NOTES_HEAD')
        </span>
        <button class="btn btn-success btn-sm ms-2" role="button"
                data-bs-toggle="collapse" href="#cardNotesBody"
                aria-expanded="true" aria-controls="cardNotesBody"
                data-bs-tooltip="tooltip" data-bs-placement="bottom"
                data-bs-title="@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')"
        >
            <span class="fa fa-arrow-down-up-across-line" aria-hidden="true"></span>
            <span class="visually-hidden">@lang('PANOPTICON_LBL_EXPAND_COLLAPSE')</span>
        </button>
    </h3>
    <div class="card-body collapse show" id="cardNotesBody">
        @if (empty(trim(strip_tags($this->item->notes ?? ''))))
            <div class="text-body-tertiary">
                @lang('PANOPTICON_SITE_LBL_NOTES_NONE')
            </div>
        @else
            {{ $this->item->notes }}
        @endif
        <hr>
        <a href="@route(sprintf('index.php?view=site&task=edit&id=%u', $this->item->getId()))#siteTabContentNotes">
            <span class="fa fa-fw fa-pencil" aria-hidden="true"></span>
            @lang('PANOPTICON_SITE_LBL_NOTES_EDIT')
        </a>
    </div>
</div>
