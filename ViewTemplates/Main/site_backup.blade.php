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
 * @var \Akeeba\Panopticon\Model\Site     $item
 * @var \Awf\Registry\Registry            $config
 */
$isInstalled  = $item->hasAkeebaBackup();
$installed    = $config->get('akeebabackup.info.installed', false);
$isPro        = !empty($config->get('akeebabackup.info.api'));
$backupRecord = $config->get('akeebabackup.latest');
$meta         = $backupRecord?->meta ?? null;
$tooOld       = $this->isTooOldBackup($backupRecord, $config);
?>

@if(!$isInstalled && !$installed)
    <span class="fa fa-fw fa-rectangle-xmark text-body-tertiary" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_NOT_INSTALLED')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_NOT_INSTALLED')</span>
@elseif(!$isPro)
    <span class="fa fa-fw fa-plug-circle-xmark text-body-tertiary" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_NOT_CONNECTED')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_NOT_CONNECTED')</span>
@elseif(empty($backupRecord) || empty($meta ?? null))
    <span class="fa fa-fw fa-ban text-danger" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_NONE')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_NONE')</span>
@elseif (in_array($meta, ['remote', 'ok', 'complete']) && $tooOld)
    <span class="fa fa-fw fa-hourglass-end text-danger" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_TOO_OLD')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_TOO_OLD')</span>
@elseif ($meta === 'obsolete')
    <span class="fa fa-fw fa-trash-can text-danger" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_OBSOLETE')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_OBSOLETE')</span>
@elseif ($meta === 'remote')
    <span class="fa fa-fw fa-cloud text-info" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_REMOTE')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_REMOTE')</span>
@elseif ($meta === 'ok' || $meta === 'complete')
    <span class="fa fa-fw fa-check-circle text-success" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_OK')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_OK')</span>
@elseif ($meta === 'fail')
    <span class="fa fa-fw fa-times text-danger" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_FAIL')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_FAIL')</span>
@elseif ($meta === 'pending')
    <span class="fa fa-fw fa-play text-warning" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_PENDING')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_PENDING')</span>
@else
    <span class="fa fa-fw fa-question-circle text-warning" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_UNKNOWN')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BACKUP_UNKNOWN')</span>
@endif