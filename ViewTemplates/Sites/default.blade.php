<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 * @var \Akeeba\Panopticon\Model\Site      $model
 * @var \Akeeba\Panopticon\Model\Site      $site
 */
$model = $this->getModel();
$token = $this->container->session->getCsrfToken()->getValue();
?>

<form action="@route('index.php?view=sites')" method="post" name="adminForm" id="adminForm">
    <div class="my-2 d-flex flex-column flex-lg-row gap-2 gap-lg-3 justify-content-center border rounded-1 p-2 bg-body-tertiary">
        {{-- Search --}}
        <div class="input-group" @if(empty($this->groupMap)) style="max-width: max(25em, 50%)" @endif>
            <input type="search" class="form-control form-control-lg" id="search"
                   placeholder="@lang('PANOPTICON_LBL_FORM_SEARCH')"
                   name="name" value="{{{ $model->getState('name', '') }}}">
            <label for="search" class="visually-hidden">@lang('PANOPTICON_LBL_FORM_SEARCH')</label>
            <button type="submit"
                    class="btn btn-primary">
                <span class="fa fa-search" aria-hidden="true"></span>
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </span>
            </button>
        </div>
        {{-- Groups --}}
        @if (!empty($this->groupMap))
        <div class="input-group choice-large">
            <label for="group" class="form-label visually-hidden">@lang('PANOPTICON_MAIN_LBL_FILTER_GROUPS')</label>
            {{ $this->container->html->select->genericList(
                data: array_combine(
                    array_merge([''], array_keys($this->groupMap)),
                    array_merge([$this->getLanguage()->text('PANOPTICON_MAIN_LBL_FILTER_GROUPS_PLACEHOLDER')], array_values($this->groupMap)),
                ),
                name: 'group[]',
                attribs: array_merge([
                    'class' => 'form-select js-choice',
                    'multiple' => 'multiple',
                    'style' => 'min-width: min(20em, 50%)'
                ]),
                selected: array_filter($this->getModel()->getState('group', []) ?: [])
            ) }}
            <button type="submit"
                    class="btn btn-primary">
                <span class="fa fa-search" aria-hidden="true"></span>
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </span>
            </button>
        </div>
        @endif
    </div>

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_SITES_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th width="1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
            </th>
            <th>
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_SITES_TABLE_HEAD_NAME', 'name', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th width="10%">
                @lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')
            </th>
            <th width="5%">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_LBL_TABLE_HEAD_NUM', 'id', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
        </tr>
        </thead>
        <tbody>
        <?php $i = 1 ?>
        @foreach($this->items as $site)
            <tr>
                <td>
                    {{ $this->getContainer()->html->grid->id(++$i, $site->id) }}
                </td>
                <td>
                    <div class="fw-medium">
                        <a href="@route(sprintf('index.php?view=site&task=edit&id=%d', $site->id))">
                            {{{ $site->name }}}
                        </a>
                    </div>
                    <div class="small mt-1">
                        <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_URL_SCREENREADER')</span>
                        <span class="text-secondary">
                            {{{ $site->getBaseUrl() }}}
                        </span>
                    </div>
                    {{-- Show group labels --}}
                    @if (!empty($groups = $site->getConfig()->get('config.groups')))
                        <div>
                            @foreach($groups as $gid)
                                @if (isset($this->groupMap[$gid]))
                                    <span class="badge bg-secondary">
                                    {{{ $this->groupMap[$gid] }}}
                                </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </td>
                <td>
                    @if ($site->enabled)
                        <a class="text-decoration-none text-success"
                           href="@route(sprintf('index.php?view=sites&task=unpublish&id=%d&%s=1', $site->id, $token))"
                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                           data-bs-title="@lang('PANOPTICON_LBL_PUBLISHED')"
                        >
                            <span class="fa fa-circle-check" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_LBL_PUBLISHED')</span>
                        </a>
                    @else
                        <a class="text-decoration-none text-danger"
                           href="@route(sprintf('index.php?view=sites&task=publish&id=%d&%s=1', $site->id, $token))"
                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                           data-bs-title="@lang('PANOPTICON_LBL_UNPUBLISHED')"
                        >
                            <span class="fa fa-circle-xmark" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_LBL_UNPUBLISHED')</span>
                        </a>
                    @endif
                </td>
                <td>
                    {{ (int) $site->id }}
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