<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\PhpVersion\PhpVersion;

/** @var \Akeeba\Panopticon\View\Main\Html $this */

if ($this->phpVersionInfo === null || $this->phpVersionInfo->unknown)
{
	return;
}
?>


@if ($this->phpVersionInfo->eol)
    <div class="alert alert-danger">
        <h3 class="alert-heading">
            <span class="fa fa-circle-xmark" aria-hidden="true"></span>
            @sprintf('PANOPTICON_MAIN_PHP_EOL_HEAD', PHP_VERSION)
        </h3>
        <p>
            @sprintf(
                'PANOPTICON_MAIN_PHP_EOL_BODY',
                PHP_VERSION,
                $this->phpVersionInfo->dates->eol->format($this->getLanguage()->text('DATE_FORMAT_LC')),
                (new PhpVersion())->getMinimumSupportedBranch(),
                (new PhpVersion())->getRecommendedSupportedBranch()
            )
        </p>
    </div>
@elseif (!$this->phpVersionInfo->supported)
    <div class="alert alert-warning">
        <details>
            <summary>
                <span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
                @sprintf('PANOPTICON_MAIN_PHP_SECURITY_HEAD', PHP_VERSION)
            </summary>
            <p>
                @sprintf(
                    'PANOPTICON_MAIN_PHP_SECURITY_BODY',
                    PHP_VERSION,
                    $this->phpVersionInfo->dates->eol->format($this->getLanguage()->text('DATE_FORMAT_LC')),
                    (new PhpVersion())->getRecommendedSupportedBranch()
                )
            </p>
        </details>
    </div>
@elseif (version_compare(PHP_VERSION, $this->phpVersionInfo->latest, 'lt'))
    <div class="alert alert-info">
        <details>
            <summary class="text-info">
                <span class="fa fa-info-circle" aria-hidden="true"></span>
                @lang('PANOPTICON_MAIN_PHP_UPDATE_HEAD')
            </summary>
            <p>
                @sprintf(
                    'PANOPTICON_MAIN_PHP_UPDATE_BODY',
                    PHP_VERSION,
                    $this->phpVersionInfo->latest
                )
            </p>
        </details>
    </div>
@endif
