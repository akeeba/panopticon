<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 */

$jVersion            = $config->get('core.current.version');
$stability           = $config->get('core.current.stability');
$canUpgrade          = $config->get('core.canUpgrade');
$latestJoomlaVersion = $config->get('core.latest.version');
$jUpdateFailure      = !$config->get('core.extensionAvailable') || !$config->get('core.updateSiteAvailable');
$token               = $this->container->session->getCsrfToken()->getValue();
$returnUrl           = base64_encode(\Awf\Uri\Uri::getInstance()->toString());
?>

@repeatable('joomlaVersion', $jVersion)
    {{{ $jVersion }}}
   <?php $version = Version::create($jVersion) ?>
    @if($version->isDev())
        <sup>
            <span class="badge bg-danger small text-light">
                <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_DEV_SHORT')</span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_DEV_LONG')</span>
            </span>
        </sup>
    @elseif($version->isAlpha())
        <sup>
            <span class="badge bg-danger small">
                <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_ALPHA_SHORT')</span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_ALPHA_LONG')</span>
            </span>
        </sup>
    @elseif($version->isBeta())
        <sup>
            <span class="badge bg-warning small">
                <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_BETA_SHORT')</span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BETA_LONG')</span>
            </span>
        </sup>
    @elseif($version->isRC())
        <sup>
            <span class="badge bg-info small">
                <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_RC_SHORT')</span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_RC_LONG')</span>
            </span>
        </sup>
    @else
        <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_STABLE_LONG')</span>
    @endif
@endrepeatable

<div class="d-flex flex-row gap-2">
    <a class="btn btn-sm btn-outline-secondary" role="button"
       href="@route(sprintf('index.php?view=site&task=refreshSiteInformation&id=%d&return=%s&%s=1', $item->id, $returnUrl, $token))"
       data-bs-toggle="tooltip" data-bs-placement="bottom"
       data-bs-title="@sprintf('PANOPTICON_SITE_BTN_JUPDATE_RELOAD', $item->name)"
    >
        <span class="fa fa-refresh" aria-hidden="true"></span>
        <span class="visually-hidden">@sprintf('PANOPTICON_SITE_BTN_JUPDATE_RELOAD', $item->name)</span>
    </a>
    {{-- Is Joomla Update working at all? --}}
    @if($jUpdateFailure)
        <div>
            <span class="fa fa-exclamation-triangle text-danger" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')"
            ></span>
            <span class="visually-hidden">
                @lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPDATES_BROKEN')
            </span>
        </div>
    @elseif ($item->isJoomlaUpdateTaskStuck())
        <div>
            <div class="badge bg-light text-dark"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE')"
            >
                <span class="fa fa-bell" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_STUCK_UPDATE')</span>
            </div>
        </div>
    @elseif ($item->isJoomlaUpdateTaskScheduled())
        <div>
            <div class="badge bg-info-subtle text-info"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE')"
            >
                <span class="fa fa-clock" aria-hidden="true"></span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_CORE_SCHEDULED_UPDATE')</span>
            </div>
        </div>
    @endif

    @if (empty($jVersion))
        <span class="badge bg-secondary-subtle">@lang('PANOPTICON_MAIN_SITES_LBL_JVERSION_UNKNOWN')</span>
    @else

        @if ($canUpgrade)
            <div
                    data-bs-toggle="tooltip" data-bs-placement="bottom"
                    data-bs-title="@sprintf('PANOPTICON_MAIN_SITES_LBL_JOOMLA_UPGRADABLE_TO', $latestJoomlaVersion)"

            >
                <div class="text-warning fw-bold d-inline-block">
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
                <div class="small text-success-emphasis d-inline-block">
                    <span class="fa fa-arrow-right" aria-hidden="true"></span>
                    <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_JOOMLA_CAN_BE_UPGRADED_SHORT')</span>
                    @yieldRepeatable('joomlaVersion', $latestJoomlaVersion)
                </div>
            </div>
        @else
            @if($jUpdateFailure)
                <div class="text-secondary">
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
            @else
                <div class="text-body">
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
            @endunless
        @endif

    @endif

    @if (($overridesChanged = $config->get('core.overridesChanged')) > 0)
        <div class="ms-2 small" data-bs-toggle="tooltip" data-bs-placement="bottom"
             data-bs-title="@sprintf('PANOPTICON_SITE_LBL_TEMPLATE_OVERRIDES_CHANGED_N', $overridesChanged)">
            <span class="badge bg-light-subtle text-warning border border-warning-subtle">
                <span class="fa fa-arrows-to-eye fa-fw" aria-hidden="true"></span>
                <span aria-hidden="true">{{ $overridesChanged ?? 0 }}</span>
                <span class="visually-hidden">@sprintf('PANOPTICON_SITE_LBL_TEMPLATE_OVERRIDES_CHANGED_N', $overridesChanged)</span>
            </span>
        </div>
    @endif

</div>
