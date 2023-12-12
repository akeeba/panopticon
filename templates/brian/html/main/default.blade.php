<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/** @var \Akeeba\Panopticon\View\Main\Html $this */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Main\Html $this
 * @var \Akeeba\Panopticon\Model\Site $model
 * @var \Akeeba\Panopticon\Model\Main $mainModel
 */
$model      = $this->getModel();
$mainModel  = $this->getModel('main');
$user       = $this->container->userManager->getUser();
$canCreate  = $user->getPrivilege('panopticon.admin') || $user->getPrivilege('panopticon.addown');
$isFiltered = array_reduce(
    ['search', 'coreUpdates', 'extUpdates', 'phpFamily', 'cmsFamily'],
    fn(bool $carry, string $filterKey) => $carry || $model->getState($filterKey) !== null,
    false
);

$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-choice').forEach((element) => {
        new Choices(element, {allowHTML: false, removeItemButton: true, placeholder: true, placeholderValue: ""});
    });
});

JS;

?>

@if($this->container->userManager->getUser()->getPrivilege('panopticon.super'))
<!-- @include('Main/heartbeat') -->
@include('Main/cronfellbehind')
@include('Main/php_warnings')
@include('Main/selfupdate')
@endif

@js('choices/choices.min.js', $this->getContainer()->application)
@js('js/siteSelect.js', $this->getContainer()->application, defer:true)

@inlinejs($js)

{{-- My Sites --}}
@if ($this->itemsCount || $isFiltered)
<form name="sitesForm" id="adminForm" method="post" action="@route('index.php?view=main')">

    {{-- Filters --}}
    <div class="mt-2 mb-3 border rounded-1 p-2 bg-body-tertiary align-items-center" id="siteSelectContainer">
        <div class="d-flex flex-column flex-lg-row gap-2 gap-lg-3 justify-content-center align-items-center">
            {{-- Search --}}
            <label for="siteSelectSearch" class="sr-only">@lang('PANOPTICON_LBL_FORM_SEARCH')</label>
            <div class="input-group w-50">
                <input type="search" class="form-control form-control-lg" id="siteSelectSearch"
                    placeholder="@lang('PANOPTICON_LBL_FORM_SEARCH')" name="siteSelectSearch"
                    value="{{{ $model->getState('search', '') }}}">
            </div>
        </div>
        {{-- Drop-down filters --}}
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-3 mt-2">
            {{-- coreUpdates Has Core Updates --}}
            <div>
                <label for="coreUpdates" class="form-label">@lang('PANOPTICON_MAIN_LBL_FILTER_COREUPDATES')</label>
                {{ $this->container->html->select->genericList( [
                    '' => 'PANOPTICON_MAIN_LBL_FILTER_DROPDOWN_SELECT',
                    '0' => 'AWF_NO',
                    '1' => 'AWF_YES',
                ], 'coreUpdates', [
                    'class' => 'form-select form-select-lg akeebaGridViewAutoSubmitOnChange',
                ], selected: $model->getState('coreUpdates'),
                idTag: 'coreUpdates',
                translate: true) }}
            </div>
            {{-- extUpdates Has extension updates --}}
            <div>
                <label for="extUpdates" class="form-label">@lang('PANOPTICON_MAIN_LBL_FILTER_EXTUPDATES')</label>
                {{ $this->container->html->select->genericList( [
                    '' => 'PANOPTICON_MAIN_LBL_FILTER_DROPDOWN_SELECT',
                    '0' => 'AWF_NO',
                    '1' => 'AWF_YES',
                ], 'extUpdates', [
                    'class' => 'form-select form-select-lg akeebaGridViewAutoSubmitOnChange',
                ], selected: $model->getState('extUpdates'),
                idTag: 'extUpdates',
                translate: true) }}
            </div>
            {{-- cmsFamily CMS Version --}}
            <div>
                <label class="form-label" for="cmsFamily">@lang('PANOPTICON_MAIN_LBL_FILTER_CMSFAMILY')</label>
                {{ $this->container->html->select->genericList(
                    array_merge([
                        '' => $this->getLanguage()->text('PANOPTICON_MAIN_LBL_FILTER_DROPDOWN_SELECT')
                    ], $mainModel->getKnownJoomlaVersions()),
                    'cmsFamily',
                    [
                        'class' => 'form-select form-select-lg akeebaGridViewAutoSubmitOnChange',
                    ],
                selected: $model->getState('cmsFamily'),
                idTag: 'cmsFamily',
                translate: false) }}
            </div>
            {{-- phpFamily PHP Version --}}
            <div>
                <label class="form-label" for="phpFamily">@lang('PANOPTICON_MAIN_LBL_FILTER_PHPFAMILY')</label>
                {{ $this->container->html->select->genericList(
                    array_merge([
                        '' => $this->getLanguage()->text('PANOPTICON_MAIN_LBL_FILTER_DROPDOWN_SELECT')
                    ], $mainModel->getKnownPHPVersions()),
                    'phpFamily',
                    [
                        'class' => 'form-select form-select-lg akeebaGridViewAutoSubmitOnChange',
                    ],
                selected: $model->getState('phpFamily'),
                idTag: 'phpFamily',
                translate: false) }}
            </div>
            {{-- Groups --}}
            @if (!empty($this->groupMap))
            <div>
                <label for="group" class="form-label">@lang('PANOPTICON_MAIN_LBL_FILTER_GROUPS')</label>
                {{ $this->container->html->select->genericList(
                    data: array_combine(
                        array_merge([''], array_keys($this->groupMap)),
                        array_merge([$this->getLanguage()->text('PANOPTICON_MAIN_LBL_FILTER_GROUPS_PLACEHOLDER')], array_values($this->groupMap)),
                    ),
                    name: 'group[]',
                    attribs: array_merge([
                        'class' => 'form-select js-choice akeebaGridViewAutoSubmitOnChange',
                        'multiple' => 'multiple',
                    ]),
                    selected: array_filter($this->getModel()->getState('group', []) ?: [])
                ) }}
            </div>
            @endif
        </div>
    </div>
    {{-- Results table --}}
    <?php
        /** @var \Akeeba\Panopticon\Model\Site $item */
        ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="siteSelectResultsContainer">
        @foreach($this->items as $item)
        <?php
            $url     = $item->getBaseUrl();
			$iconurl = parse_url($url, PHP_URL_HOST);
            $config  = new Awf\Registry\Registry($item->config);
            ?>
        <div class="col siteSelectServer">
            <div class="bg-body-tertiary border rounded-1 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-start">
                        <img src="https://api.faviconkit.com/{{{ $iconurl }}}/16" alt="" height="24" width="24"
                            class="rounded img-thumbnail mb-1">
                        <h5 class="ms-2 siteTitle">
                            <a class="" href="@route(sprintf('index.php?view=site&task=read&id=%s', $item->id))">
                                {{{ $item->name }}}
                            </a>
                        </h5>
                    </div>
                    {{-- Show group labels --}}
                    @if (!empty($groups = $config->get('config.groups')))
                    @foreach($groups as $gid)
                    @if (isset($this->groupMap[$gid]))
                    <span class="badge text-bg-dark m-0">
                        {{{ $this->groupMap[$gid] }}}
                    </span>
                    @endif
                    @endforeach
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fa fa-globe fa-3x" aria-hidden="true"></span>
                    <dl class="row w-75 mx-auto mb-0 lh-1">
                        <dt class="col-5">PHP Version</dt>
                        <dd class="col-7">
                            @include('Main/site_php', [
                            'item' => $item,
                            'config' => $config,
                            'php' => $config->get('core.php')
                            ])
                        </dd>

                        <dt class="col-5">Joomla</dt>
                        <dd class="col-7">
                            @include('Main/site_joomla', [
                            'item' => $item,
                            'config' => $config,
                            ])
                        </dd>

                        <dt class="col-5">Overrides</dt>
                        <dd class="col-7">
                            @include('Main/site_overrides', [
                            'item' => $item,
                            'config' => $config,
                            ])
                        </dd>

                        <dt class="col-5">Extensions</dt>
                        <dd class="col-7">
                            @include('Main/site_extensions', [
                            'item' => $item,
                            'config' => $config,
                            ])
                        </dd>
                    </dl>
                </div>
                <div class="d-flex justify-content-end">
                    <a class="text-decoration-none" href="{{{ $url }}}">
                        {{{ $url }}}
                        <span class="fa fa-external-link-square ps-1" aria-hidden="true"></span>
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <input type="hidden" name="task" id="task" value="browse">
    <input type="hidden" name="filter_order" id="filter_order" value="{{{ $this->lists->order }}}">
    <input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="{{{ $this->lists->order_Dir }}}">
</form>
@else
<div class="d-flex flex-column align-items-center gap-3 mt-4" id="siteSelectEmpty">
    <div class="text-body-tertiary">
        <span class="fa fa-globe display-1" aria-hidden="true"></span>
    </div>
    <div class="display-1">
        @lang('PANOPTICON_MAIN_SITES_LBL_NOSITES_HEAD')
    </div>
    <div class="display-6 text-center text-secondary" style="max-width: 600px">
        @lang('PANOPTICON_MAIN_SITES_LBL_NOSITES_CTA')
    </div>
    <div class="py-5 mb-2">
        <a href="@route('index.php?view=sites&task=add')" class="btn btn-primary btn-lg" role="button">
            <span class="fa fa-plus" aria-hidden="true"></span>
            @lang('PANOPTICON_MAIN_SITES_LBL_NOSITES_BUTTON')
        </a>
    </div>
</div>
@endif