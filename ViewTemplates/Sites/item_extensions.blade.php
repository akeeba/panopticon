<?php
/** @var \Akeeba\Panopticon\View\Sites\Html $this */

use Akeeba\Panopticon\Library\Version\Version;
use Awf\Registry\Registry;

$config     = ($this->item->config instanceof Registry) ? $this->item->config : (new Registry($this->item->config));
$extensions = (array) $config->get('extensions.list', []);
$token      = $this->container->session->getCsrfToken()->getValue();

uasort($extensions, fn($a, $b) => $a->name <=> $b->name);

$lastUpdateTimestamp = function () use ($config): string
{
	$timestamp = $config->get('extensions.lastAttempt');

	return $timestamp ? $this->timeAgo($timestamp) : '(never)';
};

$willAutoUpdate = function (string $key, ?string $oldVersion, ?string $newVersion): bool
{
	static $updateInfo, $globalUpdateInfo, $defaultPreference;

	if (empty($oldVersion) || empty($newVersion) || empty($key) || version_compare($oldVersion, $newVersion, 'ge'))
	{
		return false;
	}

	if ($updateInfo === null)
	{
		$updateInfo        = $this->getModel('Sysconfig')->getExtensionPreferencesAndMeta($this->item->id);
		$globalUpdateInfo  = $this->getModel('Sysconfig')->getExtensionPreferencesAndMeta();
		$defaultPreference = $this->container->appConfig->get('tasks_extupdate_install', 'none');
	}

	$updatePreference = ($updateInfo[$key]?->preference ?? '') ?: ($globalUpdateInfo[$key]?->preference ?? '') ?: $defaultPreference;
	$vOld             = Version::create($oldVersion);
	$vNew             = Version::create($newVersion);

	return match ($updatePreference)
	{
		default => false,
		'major' => true,
		'minor' => $vOld->major() === $vNew->major(),
		'patch' => $vOld->versionFamily() === $vNew->versionFamily(),
	};
}


?>
<div class="card">
    <h3 class="card-header h4 d-flex flex-row gap-1 align-items-center">
        <span class="fa fa-cubes" aria-hidden="true"></span>
        <span class="flex-grow-1">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_HEAD')</span>
        <a type="button" class="btn btn-outline-secondary btn-sm" role="button"
           href="@route(sprintf('index.php?view=site&task=refreshExtensionsInformation&id=%d&%s=1', $this->item->id, $token))"
           data-bs-toggle="tooltip" data-bs-placement="bottom"
           data-bs-title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATE_INFO')"
        >
            <span class="fa fa-refresh" aria-hidden="true"></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATE_INFO')
            </span>
        </a>
    </h3>
    <div class="card-body">
        <p class="small text-body-tertiary">
            <strong>
                @lang('PANOPTICON_SITE_LBL_EXTENSIONS_LAST_CHECKED')
            </strong>
            {{ $lastUpdateTimestamp() }}
        </p>

        {{-- TODO Show Update Schedule Information --}}
        <div class="my-3 py-5 px-2 text-center bg-body-secondary rounded-3 display-6">
            <span class="badge bg-info">TO–DO</span>
            Show Update Schedule Information
        </div>

        <table class="table table-striped table-responsive">
            <thead class="table-dark">
            <tr>
                <th>
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_NAME')
                </th>
                <th>
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_AUTHOR')
                </th>
                <th style="width: 1%">
                    @lang('PANOPTICON_SITE_LBL_EXTENSIONS_VERSION')
                </th>
            </tr>
            </thead>
            <tbody>
            @foreach($extensions as $item)
					<?php
					$key = $this->getModel('Sysconfig')
								->getExtensionShortname(
									$item->type, $item->element, $item->folder, $item->client_id
								);

					// Hide core extensions which are stupidly only ever marked as top-level extensions on core update.
					if ($this->getModel('Sysconfig')->isExcludedShortname($key))
					{
						continue;
					}

					$currentVersion    = $item->version?->current;
					$latestVersion     = $item->version?->new;
					$noUpdateSite      = !($item->hasUpdateSites ?? false);
					$missingDownloadID = ($item->downloadkey?->supported ?? false)
						&& !($item->downloadkey?->valid ?? false);
					$error             = $noUpdateSite || $missingDownloadID;
					$hasUpdate         = !empty($currentVersion) && !empty($latestVersion)
						&& ($currentVersion != $latestVersion)
						&& version_compare($currentVersion, $latestVersion, 'lt');
					?>
                <tr>
                    <td>
                        <div>
                            <span class="text-body-tertiary pe-2">
                                @if ($item->type === 'component')
                                    <span class="fa fa-puzzle-piece" aria-hidden="true"
                                          title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_COMPONENT')</span>
                                @elseif ($item->type === 'file')
                                    <span class="fa fa-file-alt" aria-hidden="true"
                                          title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_FILE')</span>
                                @elseif ($item->type === 'library')
                                    <span class="fa fa-book" aria-hidden="true"
                                          title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_LIBRARY')</span>
                                @elseif ($item->type === 'package')
                                    <span class="fa fa-boxes-packing" aria-hidden="true"
                                          title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PACKAGE')</span>
                                @elseif ($item->type === 'plugin')
                                    <span class="fa fa-plug" aria-hidden="true"
                                          title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_PLUGIN')</span>
                                @elseif ($item->type === 'module')
                                    <span class="fa fa-cube" aria-hidden="true"
                                          title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_MODULE')</span>
                                @elseif ($item->type === 'template')
                                    <span class="fa fa-paint-brush" aria-hidden="true"
                                          title="@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')"></span>
                                    <span class="visually-hidden">@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_TEMPLATE')</span>
                                @endif
                            </span>
                            @if ($error)
                                <span class="text-danger fw-medium">
                                    {{ $item->name }}
                                </span>
                            @elseif ($hasUpdate)
                                <span class="text-warning-emphasis fw-bold">
                                    {{ $item->name }}
                                </span>
                            @else
                                {{ $item->name }}
                            @endif

                            @if ($hasUpdate && !$error && $willAutoUpdate($key, $currentVersion, $latestVersion))
                                <span class="fa fa-magic-wand-sparkles text-success ms-2" aria-hidden="true"
                                      title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_AUTOUPDATE')</span>
                            @elseif ($hasUpdate && $error && $willAutoUpdate($key, $currentVersion, $latestVersion))
                                <span class="fa fa-magic text-danger ms-2" aria-hidden="true"
                                      title="@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_WILL_NOT_AUTOUPDATE')</span>
                            @endif
                        </div>
                        <div class="small text-muted font-monospace">{{ ltrim($key, 'a') }}</div>
                        @if ($error)
                            <div>
                                @if ($noUpdateSite)
                                    <span class="badge bg-warning">
                                        <span class="fa fa-globe" aria-hidden="true"></span>
                                        @lang('PANOPTICON_SITE_LBL_EXTENSIONS_UPDATESITE_MISSING')
                                    </span>
                                @elseif ($missingDownloadID)
                                    <span class="badge bg-danger">
                                        <span class="fa fa-key" aria-hidden="true"></span>
                                        @lang('PANOPTICON_SITE_LBL_EXTENSIONS_DOWNLOADKEY_MISSING')
                                    </span>
                                @endif
                            </div>
                        @elseif (($item->downloadkey?->supported ?? false) && !empty($item->downloadkey?->value ?? '') && $this->container->userManager->getUser()->getPrivilege('panopticon.admin'))
                            {{-- TODO Download Key --}}
                            <span class="fa fa-key text-muted" ></span>
                            <span class="visually-hidden">Download Key: </span>
                            <code class="download-key" tabindex="0">{{ $item->downloadkey?->value ?? '' }}</code>

                        @endif
                    </td>
                    <td class="small">
                        <div>
                            @if ($item->authorUrl)
                                <a href="{{ $item->authorUrl }}" target="_blank">
                                    {{ $item->author }}
                                </a>
                            @else
                                {{ $item->author }}
                            @endif
                        </div>
                        @if ($item->authorEmail)
                            <div class="text-muted">
                                {{ $item->authorEmail }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if ($hasUpdate && $error)
                            <strong class="text-danger-emphasis">
                                {{ $item->version->current }}
                            </strong>
                            <div class="ps-4 text-body-tertiary">
                                <span class="fa fa-arrow-right small" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANNOTINSTALL_SR')</span>
                                <span class="fw-medium small">
                                    {{ $item->version->new }}
                                </span>
                                <span class="fa fa-lock text-danger"></span>
                            </div>
                        @elseif ($hasUpdate)
                            <strong class="text-danger-emphasis">
                                {{ $item->version->current }}
                            </strong>
                            <div class="ps-4">
                                <span class="fa fa-arrow-right text-body-tertiary small" aria-hidden="true"></span>
                                <span class="visually-hidden">@lang('PANOPTICON_SITE_LBL_EXTENSIONS_NEWVERSION_CANINSTALL_SR')</span>
                                <span class="fw-medium text-info small">
                                    {{ $item->version->new }}
                                </span>
                            </div>

                            {{-- TODO Button to install the update (if not scheduled, or if schedule failed) --}}
                        @else
                            {{ $item->version->current }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

    </div>
</div>
