<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Main\Html $this */

use Akeeba\Panopticon\Library\PhpVersion\PhpVersion;

if (!$this->container->appConfig->get('phpwarnings', true)) return;

$phpVersion = new PhpVersion;
$phpVersionInfo = $phpVersion->getVersionInformation(PHP_VERSION);

if ($phpVersionInfo->unknown) return;
?>


@if ($phpVersionInfo->eol)
    <div class="alert alert-danger">
        <h3 class="alert-heading">
            <span class="fa fa-circle-xmark" aria-hidden="true"></span>
            @sprintf('PANOPTICON_MAIN_PHP_EOL_HEAD', PHP_VERSION)
        </h3>
        <p>
            @sprintf(
	            'PANOPTICON_MAIN_PHP_EOL_BODY',
	            PHP_VERSION,
	            $phpVersionInfo->dates->eol->format(\Awf\Text\Text::_('DATE_FORMAT_LC')),
	            $phpVersion->getMinimumSupportedBranch(),
	            $phpVersion->getRecommendedSupportedBranch()
            )
        </p>
    </div>
@elseif (!$phpVersionInfo->supported)
    <div class="alert alert-warning">
        <h3 class="alert-heading h6 mb-0 pb-0"
            data-bs-toggle="collapse" href="#phpWarningCollapse"
            aria-expanded="false" aria-controls="phpWarningCollapse"
            style="cursor: pointer"
        >
            <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
            @sprintf('PANOPTICON_MAIN_PHP_SECURITY_HEAD', PHP_VERSION)
        </h3>
        <p class="collapse mb-0 mt-2 pb-0" id="phpWarningCollapse">
            @sprintf(
	            'PANOPTICON_MAIN_PHP_SECURITY_BODY',
	            PHP_VERSION,
	            $phpVersionInfo->dates->eol->format(\Awf\Text\Text::_('DATE_FORMAT_LC')),
	            $phpVersion->getRecommendedSupportedBranch()
            )
        </p>
    </div>
@elseif (version_compare(PHP_VERSION, $phpVersionInfo->latest, 'lt'))
    <div class="alert alert-info">
        <h3 class="alert-heading h6 mb-1"
            data-bs-toggle="collapse" href="#phpWarningCollapse"
            aria-expanded="false" aria-controls="phpWarningCollapse"
            style="cursor: pointer"
        >
            <span class="fa fa-circle-info" aria-hidden="true"></span>
            @lang('PANOPTICON_MAIN_PHP_UPDATE_HEAD')
        </h3>
        <p class="collapse mb-0 mt-2 pb-0" id="phpWarningCollapse">
            @sprintf(
	            'PANOPTICON_MAIN_PHP_UPDATE_BODY',
	            PHP_VERSION,
	            $phpVersionInfo->latest
            )
        </p>
    </div>
@endif
