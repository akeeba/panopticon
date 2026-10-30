<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/** @var \Akeeba\Panopticon\View\Main\Html $this */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\IntegrityState;
use Akeeba\Panopticon\Library\SiteInfo\CoreChecksumsState;

/**
 * @var \Akeeba\Panopticon\View\Main\Html $this
 * @var \Akeeba\Panopticon\Model\Site     $item
 * @var \Awf\Registry\Registry            $config
 */
$integrity = CoreChecksumsState::fromConfig($config, $item->cmsType());
?>

@if($integrity === null)
@elseif($integrity['state'] === IntegrityState::NeverRun)
    <span class="fa fa-fw fa-fingerprint text-secondary" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_NEVER_RUN')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_NEVER_RUN')</span>
@elseif($integrity['state'] === IntegrityState::Ok)
    <span class="fa fa-fw fa-check-circle text-success" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_OK')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_OK')</span>
@elseif($integrity['state'] === IntegrityState::Problems)
    <?php
    $problemsText = $this->getLanguage()->plural('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_PROBLEMS_N', $integrity['modifiedCount']);
    if ($integrity['latestAttemptFailed'])
    {
    $problemsText .= ' ' . $this->getLanguage()->text('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_LATEST_FAILED');
    }
    ?>
    <a href="@route(sprintf('index.php?view=corechecksums&site_id=%d', $item->getId()))"
       class="text-decoration-none">
        <span class="fa fa-fw fa-triangle-exclamation text-warning" aria-hidden="true"
              data-bs-toggle="tooltip" data-bs-title="{{{ $problemsText }}}"
        ></span>
        <span class="visually-hidden">{{{ $problemsText }}}</span>
    </a>
@else
    <span class="fa fa-fw fa-circle-xmark text-danger" aria-hidden="true"
          data-bs-toggle="tooltip" data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_FAILED')"
    ></span>
    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_INTEGRITY_FAILED')</span>
@endif
