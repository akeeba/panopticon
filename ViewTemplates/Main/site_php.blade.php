<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\SoftwareVersions\PhpVersion;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 * @var string                $php
 */

$phpVersion = new PhpVersion;

?>
@repeatable('phpVersion', $php, $colorizePhp = true)
<?php
$phpVersion    = new PhpVersion;
$versionInfo   = $phpVersion->getVersionInformation($php);
$latestVersion = $versionInfo->latest;
$isLatest      = version_compare($php, $latestVersion, 'ge');
?>
@if($isLatest)
    {{{ $php }}}
@else
    <div class="d-inline-block"
         @if($colorizePhp)
             data-bs-toggle="tooltip" data-bs-placement="bottom"
         data-bs-title="@sprintf('PANOPTICON_MAIN_SITES_LBL_PHP_SHOULD_UPGRADE', $latestVersion)"
            @endif
    >
        @if($colorizePhp)
            <div class="text-warning fw-bold d-inline-block">
                {{{ $php }}}
            </div>
        @else
            {{{ $php }}}
        @endif
        <div class="small text-success-emphasis d-inline-block">
            <span class="fa fa-arrow-right" aria-hidden="true"></span>
            <span class="visually-hidden">@sprintf('PANOPTICON_MAIN_SITES_LBL_PHP_SHOULD_UPGRADE', $latestVersion)</span>
            {{{ $latestVersion }}}
        </div>
    </div>

@endif
@endrepeatable

@if (empty($php))
    <span class="badge bg-secondary-subtle">Unknown</span>
@elseif ($phpVersion->isEOL($php))
		<?php
		$eolDate = ($this->container->dateFactory(
			$phpVersion->getVersionInformation($php)?->dates?->eol?->format(DATE_RFC3339) ?? '2000-01-01 00:00:00'
		))
			->format($this->getLanguage()->text('DATE_FORMAT_LC3')) ?>
    <div class="text-danger text-truncate"
         data-bs-toggle="tooltip" data-bs-placement="bottom"
         data-bs-title="@sprintf('PANOPTICON_MAIN_SITES_LBL_PHP_EOL_SINCE', $eolDate)"
    >
        <span class="fa fa-circle-xmark" aria-hidden="true"></span>
        {{{ $php }}}
        <span class="visually-hidden">@sprintf('PANOPTICON_MAIN_SITES_LBL_PHP_EOL_SINCE', $eolDate)</span>
    </div>
@elseif ($phpVersion->isSecurity($php))
		<?php
		$eolDate = ($this->container->dateFactory(
			$phpVersion->getVersionInformation($php)?->dates?->eol?->format(DATE_RFC3339) ?? '2000-01-01 00:00:00'
		))
			->format($this->getLanguage()->text('DATE_FORMAT_LC3')) ?>
    <div class="text-body-tertiary text-truncate"
         data-bs-toggle="tooltip" data-bs-placement="bottom"
         data-bs-title="@sprintf('PANOPTICON_MAIN_SITES_LBL_PHP_SECURITY_MAINTENANCE', $eolDate)"
    >
        <span class="fa fa-exclamation-circle" aria-hidden="true"></span>
        @yieldRepeatable('phpVersion', $php, false)
        <span class="visually-hidden">
            @sprintf('PANOPTICON_MAIN_SITES_LBL_PHP_SECURITY_MAINTENANCE', $eolDate)
        </span>
    </div>
@elseif($phpVersion->getVersionInformation($php)->unknown)
    <span class="text-body text-truncate">{{{ $php }}}</span>
@else
    <span class="text-truncate">
    @yieldRepeatable('phpVersion', $php)
    </span>
@endif
