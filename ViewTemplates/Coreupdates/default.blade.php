<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use \Akeeba\Panopticon\Library\Enumerations\CMSType;

/**
 * @var \Akeeba\Panopticon\View\Extupdates\Html $this
 * @var \Akeeba\Panopticon\Model\Extupdates     $model
 * @var \Akeeba\Panopticon\Model\Main           $mainModel
 */
$model     = $this->getModel();
$mainModel = $this->getModel('main');
$token     = $this->container->session->getCsrfToken()->getValue();

$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-choice').forEach((element) => {
        new Choices(element, {allowHTML: false, removeItemButton: true, placeholder: true, placeholderValue: ""});
    });
});

JS;
?>
@js('choices/choices.min.js', $this->getContainer()->application)
@inlinejs($js)

<form action="@route('index.php?view=coreupdates')" method="post" name="adminForm" id="adminForm">
    <!-- Filters -->
    <div class="my-2 border rounded-1 p-2 bg-body-tertiary">
        <div class="d-flex flex-column flex-lg-row gap-2 gap-lg-3 justify-content-center align-items-center">
            {{-- Search --}}
            <div class="input-group pnp-mw-50" @if(empty($this->groupMap)) @endif>
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
                    <label for="group"
                           class="form-label visually-hidden">@lang('PANOPTICON_MAIN_LBL_FILTER_GROUPS')</label>
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

        {{-- Drop-downs --}}
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-2 mt-2">
            <!-- Site -->
            <div>
                <label class="visually-hidden" for="site_id">@lang('PANOPTICON_EXTUPDATES_LBL_SITE')</label>
                {{  $this->container->html->select->genericList(
                        $mainModel->getSiteNamesForSelect(true, 'PANOPTICON_EXTUPDATES_LBL_SITE_SELECT'),
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
            {{-- current CMS Version --}}
            <div>
                <label class="visually-hidden" for="cmsFamily">@lang('PANOPTICON_COREUPDATES_FIELD_CURRENT')</label>
                {{ $this->container->html->select->genericList(
                    array_merge([
                        '' => $this->getLanguage()->text('PANOPTICON_COREUPDATES_LBL_SELECT_CURRENT')
                    ], $mainModel->getKnownCMSVersions()),
                    'cmsFamily',
                    [
                        'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                    ],
                selected: $model->getState('cmsFamily'),
                idTag: 'cmsFamily',
                translate: false) }}
            </div>
            {{-- latest CMS Version --}}
            <div>
                <label class="visually-hidden" for="latestFamily">@lang('PANOPTICON_COREUPDATES_FIELD_LATEST')</label>
                {{ $this->container->html->select->genericList(
                    array_merge([
                        '' => $this->getLanguage()->text('PANOPTICON_COREUPDATES_LBL_SELECT_LATEST')
                    ], $mainModel->getKnownCMSVersions()),
                    'latestFamily',
                    [
                        'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                    ],
                selected: $model->getState('latestFamily'),
                idTag: 'latestFamily',
                translate: false) }}
            </div>
            {{-- phpFamily PHP Version --}}
            <div>
                <label class="visually-hidden" for="phpFamily">@lang('PANOPTICON_MAIN_LBL_FILTER_PHPFAMILY')</label>
                {{ $this->container->html->select->genericList(
                    array_merge([
                        '' => $this->getLanguage()->text('PANOPTICON_EXTUPDATES_LBL_PHPVERSION_SELECT')
                    ], $mainModel->getKnownPHPVersions()),
                    'phpFamily',
                    [
                        'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                    ],
                selected: $model->getState('phpFamily'),
                idTag: 'phpFamily',
                translate: false) }}
            </div>
        </div>
    </div>

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_COREUPDATES_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <td class="pnp-w-1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
                {{ $this->getContainer()->html->grid->checkall() }}
            </td>
            <th scope="col">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_COREUPDATES_FIELD_SITE', 'id', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th scope="col">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_COREUPDATES_FIELD_CURRENT', 'current', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th scope="col">
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_COREUPDATES_FIELD_LATEST', 'latest', $this->lists->order_Dir, $this->lists->order, 'browse') }}
            </th>
            <th scope="col">
                PHP
            </th>
        </tr>
        </thead>
        <tbody>
		<?php
		$i = 0; ?>
        @foreach ($this->items as $item)
				<?php
				/** @var \Akeeba\Panopticon\Model\Site $item */
                [$isScheduled, $isStuck, $isRunning] = match($item->cmsType()) {
					CMSType::JOOMLA => [
						$item->isJoomlaUpdateTaskScheduled(),
                        $item->isJoomlaUpdateTaskStuck(),
                        $item->isJoomlaUpdateTaskRunning()
                    ],
					CMSType::WORDPRESS => [
						$item->isWordPressUpdateTaskScheduled(),
                        $item->isWordPressUpdateTaskStuck(),
                        $item->isWordPressUpdateTaskRunning()
                    ]
                }
				?>
            <tr>
                <td>
                    <label for="cb{{{ ++$i }}}" class="visually-hidden">
                        @sprintf('PANOPTICON_COREUPDATES_LBL_SELECT_SITE', $item->name)
                    </label>
                    <input type="checkbox" id="cb{{{ $i }}}" name="eid[]"
                           value="{{{ (int)$item->getId() }}}"
                           onclick="akeeba.System.isChecked(this.checked);" />
                </td>
                <th scope="row">
                    @if ($isStuck)
                        @if ($item->cmsType() === CMSType::JOOMLA)
                            <div class="badge bg-light text-dark"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE')"
                            >
                                <span class="fa fa-bell" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE')</span>
                            </div>
                        @elseif($item->cmsType() === CMSType::WORDPRESS)
                            <div class="badge bg-light text-dark"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE_WP')"
                            >
                                <span class="fa fa-bell" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE_WP')</span>
                            </div>
                        @endif
                    @elseif ($isRunning)
                        @if ($item->cmsType() === CMSType::JOOMLA)
                            <div class="badge bg-info-subtle text-primary"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE')"
                            >
                                <span class="fa fa-play" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE')</span>
                            </div>
                        @elseif($item->cmsType() === CMSType::WORDPRESS)
                            <div class="badge bg-info-subtle text-primary"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE_WP')"
                            >
                                <span class="fa fa-play" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_RUNNING_UPDATE_WP')</span>
                            </div>
                        @endif
                    @elseif ($isScheduled)
                        @if ($item->cmsType() === CMSType::JOOMLA)
                            <div class="badge bg-info-subtle text-info"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE')"
                            >
                                <span class="fa fa-clock" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE')</span>
                            </div>
                        @elseif($item->cmsType() === CMSType::WORDPRESS)
                            <div class="badge bg-info-subtle text-info"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE_WP')"
                            >
                                <span class="fa fa-clock" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE_WP')</span>
                            </div>
                        @endif
                    @endif

                    <div class="d-inline-block me-1">
                        @include('Common/sitetype', ['site' => $item])
                    </div>
                    <a class="fw-medium"
                       href="@route(sprintf('index.php?view=site&task=read&id=%s', $item->id))">
                        {{{ $item->name }}}
                    </a>
                    <div class="small mt-1">
                        <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_URL_SCREENREADER')</span>
                        <a href="{{{ $item->getBaseUrl() }}}" class="link-secondary text-decoration-none fw-normal"
                           target="_blank">
                            {{{ $item->getBaseUrl() }}}
                            <span class="fa fa-external-link-alt fa-xs text-muted" aria-hidden="true"></span>
                        </a>
                    </div>
                    {{-- Show group labels --}}
                    @if (!empty($groups = $item->getConfig()->get('config.groups')))
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
                </th>
                <td>
                    {{{ $item->getConfig()->get('core.current.version')  }}}
                </td>
                <td>
                    {{{ $item->getConfig()->get('core.latest.version')  }}}
                </td>
                <td>
                    {{{ $item->getConfig()->get('core.php')  }}}
                </td>
            </tr>
        @endforeach
        @if (!count($this->items))
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
