<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */

$config          = $this->item->getConfig();
$extensionsList  = $config->get('extensions.list');
$noExtensions    = empty($extensionsList);
$hasAkeebaBackup = !$noExtensions && $this->item->hasAkeebaBackup(true);
$info            = $config->get('akeebabackup.info');
$endpointOptions = $config->get('akeebabackup.endpoint');
$disable         = $noExtensions || !$hasAkeebaBackup || empty($info)
                   || (!empty($info?->api)
                       && empty($endpointOptions));
try
{
	$profiles = $disable ? [] : $this->item->akeebaBackupGetProfiles();
}
catch (Exception $e)
{
	$profiles = [];
}
$disable         = $disable || empty($profiles);
?>
<div id="backupOnUpdateInterface">
    <h4 class="border-top pt-2">
        @lang('PANOPTICON_SITES_LBL_BOU_HEAD')
    </h4>
    <p class="fs-5 text-secondary fst-italic mb-3">
        @lang('PANOPTICON_SITES_LBL_BOU_POWERED_BY')
    </p>

    <div class="text-center my-5 d-none" id="backupOnUpdateSpinner">
        <div class="display-1 my-5 text-primary">
            <span class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></span>
        </div>
        <p class="display-5 text-body-tertiary">
            Loading…
        </p>
    </div>

    <div id="backupOnUpdateInteractive">
        <div class="alert alert-info {{ $noExtensions ? '' : 'd-none' }}" id="backupOnUpdateNoExtensionsList">
            <h5 class="alert-heading">
                <span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_BOU_NO_EXT_HEAD')
            </h5>
            <p>
                @lang('PANOPTICON_SITES_LBL_BOU_NO_EXT_BODY')
            </p>
        </div>

        <div class="alert alert-danger {{ (!$noExtensions && !$hasAkeebaBackup) ? '' : 'd-none' }}"
             id="backupOnUpdateNoAkeebaBackup">
            <h5 class="alert-heading">
                <span class="fa fa-fw fa-xmark-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_BOU_NO_ABP_HEAD')
            </h5>
            <p>
                @lang('PANOPTICON_SITES_LBL_BOU_NO_ABP_BODY')
            </p>
            <div class="d-flex flex-column gap-4 flex-lg-row gap-lg-2 align-items-center justify-content-between">
                <button type="button" id="backupOnUpdateReload"
                        class="btn btn-primary"
                >
                    <span class="fa fa-fw fa-retweet" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_BOU_BTN_RELOAD_EXT')
                </button>

                <a href="https://www.akeeba.com/products/akeeba-backup.html"
                   target="_blank"
                   class="btn btn-info btn-sm">
                    <span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_BOU_BTN_LEARN_MORE')
                </a>
            </div>
        </div>

        <div class="alert alert-warning {{ !$noExtensions && $hasAkeebaBackup && $disable ? '' : 'd-none' }}"
             id="backupOnUpdateNotLinked">
            <h5 class="alert-heading">
                <span class="fa fa-fw fa-link-slash" aria-hidden="true"></span>
                @lang('PANOPTICON_SITES_LBL_BOU_NO_LINK_HEAD')
            </h5>
            <p>
                @lang('PANOPTICON_SITES_LBL_BOU_NO_LINK_BODY')
            </p>
            <div>
                <button type="button" id="backupOnUpdateRelink"
                        class="btn btn-primary"
                >
                    <span class="fa fa-fw fa-retweet" aria-hidden="true"></span>
                    @lang('PANOPTICON_SITES_LBL_BOU_BTN_LINK')
                </button>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" value="1"
                           value="1"
                           name="config[config.core_update.backup_on_update]" id="config_core_update_backup_on_update"
                           {{ !$disable && $config->get('config.core_update.backup_on_update', false) ? 'checked' : '' }}
                           @if ($disable) disabled="disabled" @endif
                    >
                    <label class="form-check-label" for="config_core_update_backup_on_update">
                        @lang('PANOPTICON_SITES_LBL_BOU_TOGGLE')
                    </label>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SITES_LBL_BOU_TOGGLE_HELP')
                </div>
            </div>
        </div>

        <div class="row mb-3" {{ $this->showOn('config[config.core_update.backup_on_update]:1') }}>
            <label for="backupOnUpdateProfiles" class="col-sm-3 col-form-label">
                @lang('PANOPTICON_SITES_LBL_BOU_PROFILE')
            </label>
            <div class="col-sm-9">
                <div class="input-group">
                    {{ $this->getContainer()->html->select->genericList(
                        data: array_combine(
                            array_map(fn($p) => $p->id, $profiles),
                            array_map(fn($p) => sprintf('#%d. %s', $p->id, $p->name), $profiles),
                        ),
                        name: 'config[config.core_update.backup_profile]',
                        attribs: [
                            'class' => 'form-control'
                        ],
                        selected: $config->get('config.core_update.backup_profile', 1),
                        idTag: 'backupOnUpdateProfiles'
                    ) }}
                    <button type="button" id="backupOnUpdateReloadProfiles"
                            class="btn btn-outline-primary"
                    >
                        <span class="fa fa-fw fa-refresh" aria-hidden="true"></span>
                        @lang('PANOPTICON_SITES_LBL_BOU_RELOAD_PROFILES')
                    </button>
                </div>
                <div class="form-text">
                    @lang('PANOPTICON_SITES_LBL_BOU_PROFILE_HELP')
                </div>
            </div>
        </div>
    </div>
</div>