<?php
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
            <span class="badge bg-danger-subtle small text-dark">
                <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_ALPHA_SHORT')</span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_ALPHA_LONG')</span>
            </span>
        </sup>
    @elseif($version->isBeta())
        <sup>
            <span class="badge bg-warning-subtle small text-dark">
                <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_BETA_SHORT')</span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_BETA_LONG')</span>
            </span>
        </sup>
    @elseif($version->isRC())
        <sup>
            <span class="badge bg-info-subtle small text-dark">
                <span aria-hidden="true">@lang('PANOPTICON_MAIN_SITES_LBL_RC_SHORT')</span>
                <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_RC_LONG')</span>
            </span>
        </sup>
    @else
        <span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_STABLE_LONG')</span>
    @endif
@endrepeatable

<div class="d-flex flex-row gap-2">
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
</div>


