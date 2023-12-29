<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Groups\Html $this
 * @var \Akeeba\Panopticon\Model\Groups     $model
 */
$model = $this->getModel();
$token = $this->container->session->getCsrfToken()->getValue();

?>
<form action="@route('index.php?view=groups')" method="post" name="adminForm" id="adminForm">
    <div class="my-2 d-flex flex-row justify-content-center border rounded-1 p-2 bg-body-tertiary">
        <div class="input-group pnp-mw-50">
            <input type="search" class="form-control form-control-lg" id="search"
                   placeholder="@lang('PANOPTICON_LBL_FORM_SEARCH')"
                   name="title" value="{{{ $model->getState('title', '') }}}">
            <label for="search" class="visually-hidden">@lang('PANOPTICON_LBL_FORM_SEARCH')</label>
            <button type="submit"
                    class="btn btn-primary">
                <span class="fa fa-search" aria-hidden="true"></span>
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </span>
            </button>
        </div>
    </div>

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_GROUPS_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th class="pnp-w-1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
            </th>
            <th>
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_GROUPS_TABLE_HEAD_TITLE', 'title', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th class="pnp-w-5">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_LBL_TABLE_HEAD_NUM', 'id', $this->lists->order_Dir, $this->lists->order, 'browse', attribs: [
                    'aria-label' => $this->getLanguage()->text('PANOPTICON_LBL_TABLE_HEAD_NUM_SR')
                ]) }}
            </th>
        </tr>
        </thead>
        <tbody>
	    <?php $i = 1 ?>
        @foreach($this->items as $group)
        <tr>
            <td>
                {{ $this->getContainer()->html->grid->id(++$i, $group->id) }}
            </td>
            <td>
                <a href="@route(sprintf('index.php?view=group&task=edit&id=%d', $group->id))">
                    {{{ $group->title }}}
                </a>
            </td>
            <td>
                {{{ $group->id }}}
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
    <input type="hidden" name="token" value="@token()">

</form>