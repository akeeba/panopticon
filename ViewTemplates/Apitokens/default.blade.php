<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Apitokens\Html $this
 * @var \Akeeba\Panopticon\Model\Apitoken      $model
 */
$model = $this->getModel();
$token = $this->container->session->getCsrfToken()->getValue();
$i     = 1;
?>

<form action="@route('index.php?view=apitokens')" method="post" name="adminForm" id="adminForm">

    @if (!$this->container->userManager->getUser()->getPrivilege('panopticon.super'))
        @if ($this->isZeroLimit)
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-2">
                <span class="fa fa-ban" aria-hidden="true"></span>
                @lang('PANOPTICON_APITOKENS_LBL_API_ACCESS_DENIED')
            </div>
        @else
            <div class="alert alert-info d-flex align-items-center gap-2 mb-2">
                <span class="fa fa-circle-info" aria-hidden="true"></span>
                @sprintf('PANOPTICON_APITOKENS_LBL_QUOTA_STATUS', $this->tokenCount, $this->tokenLimit)
            </div>
        @endif
    @endif

    <div class="my-2 border rounded-1 p-2 bg-body-tertiary">
        <div class="d-flex flex-row justify-content-center">
            <div class="input-group pnp-mw-50">
                <input type="search" class="form-control form-control-lg" id="search"
                       placeholder="@lang('PANOPTICON_APITOKENS_LBL_SEARCH_DESCRIPTION')"
                       name="search" value="{{{ $model->getState('search', '') }}}">
                <label for="search" class="visually-hidden">@lang('PANOPTICON_APITOKENS_LBL_SEARCH_DESCRIPTION')</label>
                <button type="submit" class="btn btn-primary">
                    <span class="fa fa-search" aria-hidden="true"></span>
                    <span class="visually-hidden">@lang('PANOPTICON_LBL_FORM_SEARCH')</span>
                </button>
            </div>
        </div>
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-2 mt-2">
            <div>
                <label class="visually-hidden" for="enabled">@lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')</label>
                {{ $this->container->html->select->genericList([
                    ''  => 'PANOPTICON_APITOKENS_LBL_SELECT_ENABLED',
                    '0' => 'PANOPTICON_LBL_UNPUBLISHED',
                    '1' => 'PANOPTICON_LBL_PUBLISHED',
                ], 'enabled', [
                    'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                ], selected: $model->getState('enabled'),
                idTag: 'enabled',
                translate: true) }}
            </div>
            <div>
                <label class="visually-hidden" for="created_after">@lang('PANOPTICON_APITOKENS_LBL_FILTER_CREATED_AFTER')</label>
                <input type="date" class="form-control akeebaGridViewAutoSubmitOnChange"
                       name="created_after" id="created_after"
                       value="{{{ $model->getState('created_after', '') }}}"
                       placeholder="@lang('PANOPTICON_APITOKENS_LBL_FILTER_CREATED_AFTER')"
                       aria-label="@lang('PANOPTICON_APITOKENS_LBL_FILTER_CREATED_AFTER')">
            </div>
            <div>
                <label class="visually-hidden" for="expires_before">@lang('PANOPTICON_APITOKENS_LBL_FILTER_EXPIRES_BEFORE')</label>
                <input type="date" class="form-control akeebaGridViewAutoSubmitOnChange"
                       name="expires_before" id="expires_before"
                       value="{{{ $model->getState('expires_before', '') }}}"
                       placeholder="@lang('PANOPTICON_APITOKENS_LBL_FILTER_EXPIRES_BEFORE')"
                       aria-label="@lang('PANOPTICON_APITOKENS_LBL_FILTER_EXPIRES_BEFORE')">
            </div>
            <div>
                <label class="visually-hidden" for="last_used_before">@lang('PANOPTICON_APITOKENS_LBL_FILTER_LAST_USED_BEFORE')</label>
                <input type="date" class="form-control akeebaGridViewAutoSubmitOnChange"
                       name="last_used_before" id="last_used_before"
                       value="{{{ $model->getState('last_used_before', '') }}}"
                       placeholder="@lang('PANOPTICON_APITOKENS_LBL_FILTER_LAST_USED_BEFORE')"
                       aria-label="@lang('PANOPTICON_APITOKENS_LBL_FILTER_LAST_USED_BEFORE')">
            </div>
        </div>
    </div>

    <table class="table table-striped align-middle pnp-stacked" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_APITOKENS_TITLE')
        </caption>
        <thead>
        <tr>
            <th class="pnp-w-1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
            </th>
            <th>
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_APITOKENS_TABLE_HEAD_DESCRIPTION', 'description', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th class="pnp-w-5">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_LBL_TABLE_HEAD_ENABLED', 'enabled', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th>
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_APITOKENS_TABLE_HEAD_LAST_USED', 'last_used_at', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th class="pnp-w-5">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_LBL_TABLE_HEAD_NUM', 'id', $this->lists->order_Dir, $this->lists->order, 'browse', attribs: [
                    'aria-label' => $this->getLanguage()->text('PANOPTICON_LBL_TABLE_HEAD_NUM_SR')
                ]) }}
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach($this->items as $row)
            <tr>
                {{-- Checkbox --}}
                <td>
                    {{ $this->getContainer()->html->grid->id(++$i, $row->id) }}
                </td>
                {{-- Description + expiry sub-line --}}
                <td data-label="@lang('PANOPTICON_APITOKENS_TABLE_HEAD_DESCRIPTION')">
                    <a href="@route(sprintf('index.php?view=apitoken&task=edit&id=%d', $row->id))" class="fw-medium">
                        {{{ $row->description ?: '—' }}}
                    </a>
                    @if (!empty($row->expires_at) && $row->expires_at !== '0000-00-00 00:00:00')
                        <?php
                            $expiresFormatted = $this->getContainer()->html->basic->date(
                                $row->expires_at,
                                $this->getLanguage()->text('DATE_FORMAT_LC2')
                            );
                        ?>
                        <div class="small text-muted">
                            @sprintf('PANOPTICON_APITOKENS_LBL_EXPIRES_ON', $expiresFormatted)
                        </div>
                    @endif
                </td>
                {{-- Enabled (clickable icon) --}}
                <td data-label="@lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')">
                    @if ($row->enabled)
                        <a class="text-decoration-none text-success"
                           href="@route(sprintf('index.php?view=apitokens&task=unpublish&id=%d&%s=1', $row->id, $token))"
                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                           data-bs-title="@lang('PANOPTICON_LBL_PUBLISHED')"
                        >
                            <span class="fa fa-circle-check" aria-hidden="true"></span>
                            <span class="visually-hidden">@sprintf('PANOPTICON_LBL_PUBLISHED_SR', $row->id)</span>
                        </a>
                    @else
                        <a class="text-decoration-none text-danger"
                           href="@route(sprintf('index.php?view=apitokens&task=publish&id=%d&%s=1', $row->id, $token))"
                           data-bs-toggle="tooltip" data-bs-placement="bottom"
                           data-bs-title="@lang('PANOPTICON_LBL_UNPUBLISHED')"
                        >
                            <span class="fa fa-circle-xmark" aria-hidden="true"></span>
                            <span class="visually-hidden">@sprintf('PANOPTICON_LBL_UNPUBLISHED_SR', $row->id)</span>
                        </a>
                    @endif
                </td>
                {{-- Last Used + IP sub-line --}}
                <td data-label="@lang('PANOPTICON_APITOKENS_TABLE_HEAD_LAST_USED')">
                    @if (!empty($row->last_used_at) && $row->last_used_at !== '0000-00-00 00:00:00')
                        {{ $this->getContainer()->html->basic->date($row->last_used_at, $this->getLanguage()->text('DATE_FORMAT_LC2')) }}
                        @if (!empty($row->last_used_ip))
                            <?php
                                $printableIp = @inet_ntop($row->last_used_ip);
                            ?>
                            @if ($printableIp !== false && $printableIp !== null)
                                <div class="small text-muted font-monospace">
                                    {{{ $printableIp }}}
                                </div>
                            @endif
                        @endif
                    @else
                        <span class="text-body-tertiary">—</span>
                    @endif
                </td>
                {{-- ID --}}
                <td class="font-monospace text-end" data-label="@lang('PANOPTICON_LBL_TABLE_HEAD_NUM')">
                    {{ (int) $row->id }}
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
