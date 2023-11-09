<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Version\Version;

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Extupdates\Html $this
 * @var \Akeeba\Panopticon\Model\Extupdates     $model
 * @var \Akeeba\Panopticon\Model\Main           $mainModel
 */
$model     = $this->getModel();
$mainModel = $this->getModel('main');
$token     = $this->container->session->getCsrfToken()->getValue();

$willAutoUpdate = function (string $key, ?string $oldVersion, ?string $newVersion, \Akeeba\Panopticon\Model\Site $site): bool {
	static $updateInfo, $globalUpdateInfo, $defaultPreference;

	if (empty($oldVersion) || empty($newVersion) || empty($key) || version_compare($oldVersion, $newVersion, 'ge'))
	{
		return false;
	}

	if ($updateInfo === null)
	{
		$updateInfo        = $this->getModel('Sysconfig')->getExtensionPreferencesAndMeta($site->id);
		$globalUpdateInfo  = $this->getModel('Sysconfig')->getExtensionPreferencesAndMeta();
		$defaultPreference = $this->container->appConfig->get('tasks_extupdate_install', 'none');
	}

	$updatePreference = ($updateInfo[$key]?->preference ?? '')
		?: ($globalUpdateInfo[$key]?->preference ?? '')
			?: $defaultPreference;
	$vOld             = Version::create($oldVersion);
	$vNew             = Version::create($newVersion);

	return match ($updatePreference)
	{
		default => false,
		'major' => true,
		'minor' => $vOld->major() === $vNew->major(),
		'patch' => $vOld->versionFamily() === $vNew->versionFamily(),
	};
};

?>
<form action="@route('index.php?view=extupdates')" method="post" name="adminForm" id="adminForm">
    <!-- Filters -->
    <div class="my-2 border rounded-1 p-2 bg-body-tertiary">
        <!-- Search -->
        <div class="d-flex flex-row justify-content-center">
            <div class="input-group" style="max-width: max(50%, 25em)">
                <input type="search" class="form-control" id="search"
                       placeholder="@lang('PANOPTICON_LBL_FORM_SEARCH')"
                       name="name" value="{{{ $model->getState('name', '') }}}">
                <label for="search" class="sr-only">@lang('PANOPTICON_LBL_FORM_SEARCH')</label>
                <button type="submit"
                        class="btn btn-primary">
                    <span class="fa fa-search" aria-hidden="true"></span>
                    <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_FORM_SEARCH')
                </span>
                </button>
            </div>
        </div>
        <!-- Site Filters -->
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-2 mt-2">
            <!-- Site -->
            <div>
                <label class="visually-hidden" for="manual">@lang('PANOPTICON_EXTUPDATES_LBL_SITE')</label>
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
            {{-- cmsFamily CMS Version --}}
            <div>
                <label class="visually-hidden" for="cmsFamily">@lang('PANOPTICON_MAIN_LBL_FILTER_CMSFAMILY')</label>
                {{ $this->container->html->select->genericList(
                    array_merge([
                        '' => \Awf\Text\Text::_('PANOPTICON_EXTUPDATES_LBL_CMSVERSION_SELECT')
                    ], $mainModel->getKnownJoomlaVersions()),
                    'cmsFamily',
                    [
                        'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                    ],
                selected: $model->getState('cmsFamily'),
                idTag: 'cmsFamily',
                translate: false) }}
            </div>
            {{-- phpFamily PHP Version --}}
            <div>
                <label class="visually-hidden" for="phpFamily">@lang('PANOPTICON_MAIN_LBL_FILTER_PHPFAMILY')</label>
                {{ $this->container->html->select->genericList(
                    array_merge([
                        '' => \Awf\Text\Text::_('PANOPTICON_EXTUPDATES_LBL_PHPVERSION_SELECT')
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
        <!-- Extension Filters -->
        <div class="d-flex flex-column flex-lg-row justify-content-lg-center gap-2 mt-2">
            <div>
                <label class="visually-hidden" for="manual">@lang('PANOPTICON_EXTUPDATES_LBL_EXT_NAME')</label>
                {{  $this->container->html->select->genericList(
                        $model->getExtensionNames(true),
                        'extension_name',
                        [
                            'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                        ],
                        selected: $model->getState('extension_name'),
                        idTag: 'extension_name',
                        translate: false
                    )
                }}
            </div>
            <div>
                <label class="visually-hidden" for="manual">@lang('PANOPTICON_EXTUPDATES_LBL_EXT_AUTHOR')</label>
                {{  $this->container->html->select->genericList(
                        $model->getExtensionAuthors(true),
                        'extension_author',
                        [
                            'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                        ],
                        selected: $model->getState('extension_author'),
                        idTag: 'extension_author',
                        translate: false
                    )
                }}
            </div>
            <div>
                <label class="visually-hidden" for="manual">@lang('PANOPTICON_EXTUPDATES_LBL_EXT_AUTHOR_URL')</label>
                {{  $this->container->html->select->genericList(
                        $model->getExtensionAuthorURLs(true),
                        'extension_author_url',
                        [
                            'class' => 'form-select akeebaGridViewAutoSubmitOnChange',
                        ],
                        selected: $model->getState('extension_author_url'),
                        idTag: 'extension_author_url',
                        translate: false
                    )
                }}
            </div>
        </div>
    </div>

    <table class="table table-striped align-middle" id="adminList" role="table">
        <caption class="visually-hidden">
            @lang('PANOPTICON_EXTUPDATES_TABLE_COMMENT')
        </caption>
        <thead>
        <tr>
            <th width="1">
                <span class="visually-hidden">
                    @lang('PANOPTICON_LBL_TABLE_HEAD_GRID_SELECT')
                </span>
                {{ $this->getContainer()->html->grid->checkall() }}
            </th>
            <th>
                {{ $this->getContainer()->html->grid->sort('PANOPTICON_EXTUPDATES_FIELD_SITE', 'id', $this->lists->order_Dir, $this->lists->order, 'main') }}
            </th>
            <th>
                @lang('PANOPTICON_EXTUPDATES_FIELD_EXTENSION')
            </th>
            <th>
                @lang('PANOPTICON_EXTUPDATES_FIELD_VERSION')
            </th>
            <th>
                @lang('PANOPTICON_EXTUPDATES_FIELD_AUTHOR')
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach($this->items as $i => $item)
				<?php
				/** @var \Akeeba\Panopticon\Model\Site $site */
				$site              = $this->sites[$item->site_id];
				$key               = $this->getModel('Sysconfig')
					->getExtensionShortname(
						$item->type, $item->element, $item->folder, $item->client_id
					);
				$currentVersion    = $item->version?->current;
				$latestVersion     = $item->version?->new;
				$missingDownloadID = ($item->downloadkey?->supported ?? false)
				                     && !($item->downloadkey?->valid ?? false);
				$naughtyUpdates    = $item->naughtyUpdates === 'parent';
				$error             = $missingDownloadID || $naughtyUpdates;
				$hasUpdate         = !empty($currentVersion) && !empty($latestVersion)
				                     && ($currentVersion != $latestVersion)
				                     && version_compare($currentVersion, $latestVersion, 'lt');
				?>
            <tr>
                <td>
                    <label for="cb{{{ $i }}}" class="visually-hidden">
                        @sprintf('PANOPTICON_EXTUPDATES_LBL_SELECT_EXTENSION', $item->name, $site->name)
                    </label>
                    <input type="checkbox" id="cb{{{ $i }}}" name="eid[]"
                           value="{{{ (int)$item->site_id . '_' . (int)$item->extension_id  }}}"
                           onclick="akeeba.System.isChecked(this.checked);" />
                </td>
                <td>
                    <a class="fw-medium"
                       href="@route(sprintf('index.php?view=site&task=read&id=%s', $site->getId()))">
                        {{{ $site->name }}}
                    </a>
                    <div class="text-body-secondary">
                        <span class="fab fa-fw fa-joomla text-info" aria-hidden="true"></span>
                        {{{ $site->getConfig()->get('core.current.version')  }}}
                        &nbsp;
                        <span class="fab fa-fw fa-php text-primary" aria-hidden="true"></span>
                        {{{ $site->getConfig()->get('core.php')  }}}
                    </div>
                    <div class="small mt-1">
                        <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_URL_SCREENREADER')</span>
                        <a href="{{{ $site->getBaseUrl() }}}" class="link-secondary text-decoration-none"
                           target="_blank">
                            {{{ $site->getBaseUrl() }}}
                            <span class="fa fa-external-link-square" aria-hidden="true"></span>
                        </a>
                    </div>
                </td>
                <td>
                    <div>
                    <span class="text-body-tertiary pe-2">
                                @if ($item->type === 'component')
                            <span class="fa fa-puzzle-piece" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="right"
                                  data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')</span>
                        @elseif ($item->type === 'file')
                            <span class="fa fa-file-alt" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="right"
                                  data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')</span>
                        @elseif ($item->type === 'library')
                            <span class="fa fa-book" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="right"
                                  data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')</span>
                        @elseif ($item->type === 'package')
                            <span class="fa fa-boxes-packing" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="right"
                                  data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')</span>
                        @elseif ($item->type === 'plugin')
                            <span class="fa fa-plug" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="right"
                                  data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')</span>
                        @elseif ($item->type === 'module')
                            <span class="fa fa-cube" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="right"
                                  data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')</span>
                        @elseif ($item->type === 'template')
                            <span class="fa fa-paint-brush" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="right"
                                  data-bs-title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')</span>
                        @endif
                    </span>
                        @if ($error)
                            <span class="text-danger fw-medium">
                            {{{ $item->name }}}
                        </span>
                        @elseif ($hasUpdate)
                            <span class="fw-bold">
                            {{{ $item->name }}}
                        </span>
                        @else
                            <s>{{{ $item->name }}}</s>
                        @endif

                        @if (in_array($item->extension_id, $this->scheduledPerSite[$site->getId()]))
                            <span class="badge bg-success">
                            <span class="fa fa-hourglass-half" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="top"
                                  data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_SCHEDULED_UPDATE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_SCHEDULED_UPDATE')</span>
                        </span>
                        @elseif ($hasUpdate && !$error && $willAutoUpdate($key, $currentVersion, $latestVersion, $site))
                            <span class="fa fa-magic-wand-sparkles text-success ms-2" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="top"
                                  data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')</span>
                        @elseif ($hasUpdate && $error && $willAutoUpdate($key, $currentVersion, $latestVersion, $site))
                            <span class="fa fa-magic text-danger ms-2" aria-hidden="true"
                                  data-bs-toggle="tooltip" data-bs-placement="top"
                                  data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')</span>
                        @endif
                    </div>
                    <div class="small text-muted font-monospace">{{{ ltrim($key, 'a') }}}</div>
                    @if ($missingDownloadID)
                        <div>
                            <span class="badge bg-danger">
                                <span class="fa fa-key" aria-hidden="true"></span>
                                @lang('PANOPTICON_SITE_LBL_EXTENSIONS_DOWNLOADKEY_MISSING')
                            </span>
                        </div>
                        @if ($this->container->userManager->getUser()->authorise('panopticon.admin', $site->id))
                            <a href="@route(sprintf('index.php?view=site&task=dlkey&id=%d&extension=%d&%s=1', $site->id, $extensionId, $token))"
                               class="ms-2 btn btn-outline-primary btn-sm" role="button">
                                <span class="fa fa-pencil-square" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_BTN_EDIT')</span>
                            </a>
                        @endif
                    @endif
                </td>
                <td>
                    @if ($hasUpdate && $error)
                        <strong class="text-danger-emphasis">
                            {{{ $item->version->current }}}
                        </strong>
                        <div class="ps-4 text-body-tertiary">
                            <span class="fa fa-arrow-right small" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANNOTINSTALL_SR')</span>
                            <span class="fw-medium small">
                                {{{ $item->version->new }}}
                            </span>
                            <span class="fa fa-lock text-danger"></span>
                        </div>
                    @elseif ($hasUpdate)
                        <span class="text-muted">
                            {{{ $item->version->current }}}
                        </span>
                        <div class="ps-4">
                            <span class="fa fa-arrow-right text-body-tertiary small" aria-hidden="true"></span>
                            <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANINSTALL_SR')</span>
                            <span class="fw-medium text-success-emphasis fw-bold">
                                {{{ $item->version->new }}}
                            </span>
                        </div>
                    @else
                        {{{ $item->version->current }}}
                    @endif
                </td>
                <td class="small">
                    <div>
                        @if ($item->authorUrl)
                            <a href="{{ (str_starts_with($item->authorUrl, 'http://') || str_starts_with($item->authorUrl, 'https://') || str_starts_with($item->authorUrl, '//')) ? '' : '//' }}{{{ $item->authorUrl }}}" target="_blank">
                                {{{ $item->author }}}
                            </a>
                        @else
                            {{{ $item->author }}}
                        @endif
                    </div>
                    @if ($item->authorEmail)
                        <div class="text-muted">
                            {{{ $item->authorEmail }}}
                        </div>
                    @endif
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
    <input type="hidden" name="task" id="task" value="main">
    <input type="hidden" name="filter_order" id="filter_order" value="{{{ $this->lists->order }}}">
    <input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="{{{ $this->lists->order_Dir }}}">
    <input type="hidden" name="token" value="@token()">
</form>