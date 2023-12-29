<?php

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Reports\Html $this
 * @var \Akeeba\Panopticon\Model\Reports $model
 */
$model      = $this->getModel();
$hasAvatars = $this->getContainer()->appConfig->get('avatars', false);
?>

<form action="@route('index.php?view=reports')" method="post" name="adminForm" id="adminForm">
    <!-- Filters -->
    <div class="my-2 border rounded-1 p-2 bg-body-tertiary d-print-none">
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-2 mt-2">
            <!-- Site -->
            <div>
                <label class="visually-hidden" for="site_id">@lang('PANOPTICON_EXTUPDATES_LBL_SITE')</label>
                {{  $this->container->html->select->genericList(
                        $this->getModel('main')->getSiteNamesForSelect(true, 'PANOPTICON_EXTUPDATES_LBL_SITE_SELECT'),
                        'site_id',
                        [
                            'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                        ],
                        selected: $model->getState('site_id'),
                        idTag: 'site_id',
                        translate: false
                    )
                }}
            </div>
            <div>
                <div class="input-group">
                    <label class="visually-hidden" for="from_date">@lang('PANOPTICON_REPORTS_FIELD_CREATED_ON_FROM')</label>
                    <input type="datetime-local" name="from_date" id="from_date"
                           class="form-control"
                           pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}"
                           value="{{  $model->getState('from_date') ?
                                $this->getContainer()->html->basic->date($model->getState('from_date'), 'Y-m-d\TH:i:s', 'UTC')
                                : '' }}"
                    >
                    <span class="input-group-text">GMT</span>
                </div>
            </div>
            <div>
                <div class="input-group">
                    <label class="visually-hidden" for="to_date">@lang('PANOPTICON_REPORTS_FIELD_CREATED_ON_TO')</label>
                    <input type="datetime-local" name="to_date" id="to_date"
                           class="form-control"
                           pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}"
                           value="{{  $model->getState('to_date') ?
                                $this->getContainer()->html->basic->date($model->getState('to_date'), 'Y-m-d\TH:i:s', 'UTC')
                                : '' }}"
                    >
                    <span class="input-group-text">GMT</span>
                </div>
            </div>
            <div>
                <button type="submit"
                class="btn btn-primary">
                    <span class="fa fa-fw fa-search" aria-hidden="true"></span>
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </button>
            </div>
        </div>
    </div>

    @if($this->hasSiteFilter)
        <h3 class="mt-4 mb-2 border-bottom border-primary">{{{ $this->items->first()?->site_id?->name ?? '' }}}</h3>
        <p class="text-secondary">
            {{{ $this->items->first()?->site_id?->getBaseUrl() ?? '' }}}
        </p>
    @endif

    <table class="table table-striped">
        <caption class="visually-hidden">
            @lang('PANOPTICON_REPORTS_LBL_TABLE_CAPTION')
        </caption>
        <thead>
        <tr>
            @unless($this->hasSiteFilter)
            <th scope="col">
                @lang('PANOPTICON_REPORTS_FIELD_SITE')
            </th>
            @endunless
            <th scope="col" class="pnp-w-10">
                @lang('PANOPTICON_REPORTS_FIELD_CREATED_ON')
            </th>
            <th scope="col" class="d-none d-lg-table-cell">
                @lang('PANOPTICON_REPORTS_FIELD_CREATED_BY')
            </th>
            <th scope="col">
                @lang('PANOPTICON_REPORTS_FIELD_ACTION')
            </th>
        </tr>
        </thead>
        <tfoot class="d-print-none">
        <tr>
            <td colspan="20" class="center">
                {{ $this->pagination->getListFooter(['class' => 'form-select akeebaGridViewAutoSubmitOnChange']) }}
            </td>
        </tr>
        </tfoot>
        <tbody>
        <?php /** @var \Akeeba\Panopticon\Model\Reports $item */ ?>
        @foreach ($this->items as $item)
        <tr>
            @unless($this->hasSiteFilter)
            <th scope="col">
                <div>
                    {{{ $item->site_id->name }}}
                </div>
                <div class="small fw-normal">
                    {{{ $item->site_id->getBaseUrl() }}}
                </div>
            </th>
            @endunless
            <td class="font-monospace small">
                {{{ $this->getContainer()->html->basic->date($item->created_on->format(DATE_ATOM), $this->getLanguage()->text('DATE_FORMAT_LC6')) }}}
            </td>
            <td>
                <div class="d-flex flex-row gap-2 align-items-center">
                @if ($item->created_by->getId() == 0)
                    <div>
                        <span class="fa fa-fw fa-robot fs-4 text-body-tertiary" aria-hidden="true"></span>
                    </div>
                    <div class="d-flex flex-column">
                        <div class="text-secondary small fw-medium">
                            @lang('PANOPTICON_APP_LBL_SYSTEM_TASK')
                        </div>
                        <div class="text-success small font-monospace">
                            system
                        </div>
                    </div>
                @else
                    <div>
                        @if($hasAvatars)
                        <img src="{{ $item->created_by->getAvatar(64) }}" alt="" class="rounded" style="height: 1.75em; filter: grayscale(80%)">
                        @else
                            <span class="fa fa-fw fa-user fs-4 text-body-tertiary" aria-hidden="true"></span>
                        @endif
                    </div>
                    <div class="d-flex flex-column">
                        <div class="text-secondary small fw-medium">
                            {{ $item->created_by->getName() }}
                        </div>
                        <div class="text-success small font-monospace">
                            {{ $item->created_by->getUsername() }}
                        </div>
                    </div>
                @endif
                </div>
            </td>
            <td>
                @include('Reports/default_' . $item->action->value, [
                    'item' => $item
                ])
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
    </table>

    <input type="hidden" name="boxchecked" id="boxchecked" value="0">
    <input type="hidden" name="task" id="task" value="browse">
    <input type="hidden" name="token" value="@token()">
</form>