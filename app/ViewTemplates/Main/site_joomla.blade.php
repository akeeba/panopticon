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
                <span aria-hidden="true">DEV</span>
                <span class="visually-hidden">Development Release</span>
            </span>
        </sup>
    @elseif($version->isAlpha())
        <sup>
            <span class="badge bg-danger-subtle small text-dark">
                <span aria-hidden="true">Alpha</span>
                <span class="visually-hidden">Alpha</span>
            </span>
        </sup>
    @elseif($version->isBeta())
        <sup>
            <span class="badge bg-warning-subtle small text-dark">
                <span aria-hidden="true">Beta</span>
                <span class="visually-hidden">Beta</span>
            </span>
        </sup>
    @elseif($version->isRC())
        <sup>
            <span class="badge bg-info-subtle small text-dark">
                <span aria-hidden="true">RC</span>
                <span class="visually-hidden">Release Candidate</span>
            </span>
        </sup>
    @else
        <span class="visually-hidden">Stable</span>
    @endif
@endrepeatable

<div class="d-flex flex-row gap-2">
    {{-- Is Joomla Update working at all? --}}
    @if($jUpdateFailure)
        <div>
            <span class="fa fa-exclamation-triangle text-danger" aria-hidden="true"
                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                  data-bs-title="Joomla Update is not working correctly on this site"
            ></span>
            <span class="visually-hidden">
                Joomla Update is not working correctly on this site
            </span>
        </div>
    @endif

    @if (empty($jVersion))
        <span class="badge bg-secondary-subtle">Unknown</span>
    @else

        @if ($canUpgrade)
            <div>
                <div class="text-warning fw-bold">
                    @yieldRepeatable('joomlaVersion', $jVersion)
                </div>
                <div class="small text-muted">
                    <span class="fa fa-arrow-up-right-dots" aria-hidden="true"
                          data-bs-toggle="tooltip" data-bs-placement="bottom"
                          data-bs-title="Can be upgraded to {{{ $latestJoomlaVersion }}}"
                    ></span>
                    <span class="visually-hidden">Can be upgraded to</span>
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


