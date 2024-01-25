<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Overrides\Html $this
 * @var \Akeeba\Panopticon\Model\Overrides     $model
 */
$model = $this->getModel();
$token = $this->container->session->getCsrfToken()->getValue();
$i     = 1;
$favIcon = $this->site->getFavicon(asDataUrl: true, onlyIfCached: true);

?>

<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ (int) $this->site->id }}</span>
    @if($favIcon)
        <img src="{{{ $favIcon }}}"
             style="max-width: 1em; max-height: 1em; aspect-ratio: 1.0"
             class="mx-1 p-1 border rounded"
             alt="">
    @endif
    <span class="flex-grow-1">{{{ $this->site->name }}}</span>
</h3>

<form action="@route('index.php?view=overrides')" method="post" name="adminForm" id="adminForm">
    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_OVERRIDES_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th class="pnp-w-1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
            </th>
            <th>
                @lang('PANOPTICON_OVERRIDES_LBL_FIELD_TEMPLATE')
            </th>
            <th>
                @lang('PANOPTICON_OVERRIDES_LBL_FIELD_FILE')
            </th>
            <th>
                @lang('PANOPTICON_LBL_FIELD_CREATED_ON')
            </th>
            <th>
                @lang('PANOPTICON_LBL_FIELD_MODIFIED_ON')
            </th>
            <th>
                @lang('PANOPTICON_OVERRIDES_LBL_FIELD_ACTION')
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach($this->items as $item)
            <tr>
                {{-- Checkbox --}}
                <td>
                    {{ $this->getContainer()->html->grid->id(++$i, $item->id) }}
                </td>
                {{-- Template --}}
                <td>
                    <div>
                        <div class="fw-bold text-primary-emphasis">
                            {{ $item->template }}
                        </div>
                        <div>
                            <span class="badge {{ $item->client_id == 0 ? 'bg-primary' : 'bg-secondary' }}">
                                @if($item->client_id == 0)
                                    @lang('PANOPTICON_OVERRIDES_LBL_FRONTEND')
                                @else
                                    @lang('PANOPTICON_OVERRIDES_LBL_BACKEND')
                                @endif
                            </span>
                        </div>
                    </div>
                </td>
                {{-- File --}}
                <td>
                    <a href="@route(sprintf(
                            'index.php?view=override&task=read&site_id=%s&id=%d',
                            $this->site->id,
                            $item->id
                        ))">
                        <span class="small font-monospace">
                            {{ base64_decode($item->hash_id) ?: '???' }}
                        </span>
                    </a>
                </td>
                {{-- Created --}}
                <td>
                    {{{ $this->getContainer()->html->basic->date($item->created_date, $this->getLanguage()->text('DATE_FORMAT_LC5')) }}}
                </td>
                {{-- Modified --}}
                <td>
                    {{{ $this->getContainer()->html->basic->date($item->modified_date, $this->getLanguage()->text('DATE_FORMAT_LC5')) }}}
                </td>
                {{-- Action --}}
                <td>
                    <span class="badge bg-dark">
                        {{ $item->action }}
                    </span>
                </td>
            </tr>
        @endforeach
        @if (!$this->items?->count())
            <tr>
                <td colspan="20" class="text-center text-body-tertiary">
                    @lang('AWF_PAGINATION_LBL_NO_RESULTS')
                </td>
            </tr>
        @endif
        </tbody>
        <tfoot>
        <tr>
            <td colspan="20" class="center">
                {{ $this->pagination->getListFooter(['class' => 'form-select akeebaGridViewAutoSubmitOnChange']) }}
            </td>
        </tr>
        </tfoot>
    </table>

    <input type="hidden" name="boxchecked" id="boxchecked" value="0">
    <input type="hidden" name="task" id="task" value="browse">
    <input type="hidden" name="filter_order" id="filter_order" value="{{{ $this->lists->order }}}">
    <input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="{{{ $this->lists->order_Dir }}}">
    <input type="hidden" name="site_id" id="site_id" value="{{{ (int) $this->site->id }}}">
    <input type="hidden" name="token" value="@token()">
</form>
