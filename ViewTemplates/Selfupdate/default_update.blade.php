<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Version\Version;
use Awf\Html\Html;

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */

$releaseDate = $this->latestversion->releaseDate;

if ($releaseDate instanceof DateTime)
{
	$releaseDate = $releaseDate->format(DATE_W3C);
}

?>
<div class="mt-3 mb-5 mx-2 py-4 px-2 bg-info border-info rounded-3 text-center text-white">
    <div class="display-1">
        <span class="fa fa-file-zipper" aria-hidden="true"></span>
    </div>
    <h3 class="display-3">
        @sprintf('PANOPTICON_SELFUPDATE_LBL_UPDATE_HEAD', $this->latestversion->version)
    </h3>
</div>

<p class="text-center fs-4 my-3">
    <span class="text-muted">{{ AKEEBA_PANOPTICON_VERSION }}</span>
    <span class="fa fa-arrow-right" aria-hidden="true"></span>
    <span class="visually-hidden">@lang('PANOPTICON_SELFUPDATE_LBL_UPDATE_BODY_SR')</span>
    <span class="text-success">{{{ $this->latestversion->version }}}</span>
</p>

<div class="my-5 d-flex flex-row justify-content-center align-items-center gap-3">
    <div>
        <a class="btn btn-primary btn-lg" role="button"
           href="@route('index.php?view=selfupdate&task=update')">
            <span class="fa fa-play" aria-hidden="true"></span>
            @lang('PANOPTICON_SELFUPDATE_LBL_UPGRADE_BTN')
        </a>
    </div>
    <div>
        <a class="btn btn-outline-secondary" role="button"
           href="@route('index.php?view=selfupdate&force=1')">
            <span class="fa fa-refresh" aria-hidden="true"></span>
            @lang('PANOPTICON_SELFUPDATE_BTN_RELOAD')
        </a>
    </div>
</div>

<div class="card card-body border-info bg-body-tertiary mb-5">
    <h4 class="card-title">
        <span class="fa fa-info-circle pe-1 text-info"></span>
        @lang('PANOPTICON_APP_TITLE_SHORT') {{{ $this->latestversion->version }}}
    </h4>
    <p>
        @sprintf('PANOPTICON_SELFUPDATE_LBL_UPTODATE_RELEASED', $this->getContainer()->html->basic->date($releaseDate, $this->getLanguage()->text('DATE_FORMAT_LC1')))
        <br>
        @lang('PANOPTICON_SELFUPDATE_LBL_WILL_BE_DOWNLOADED_FROM')
        <a href="{{{ $this->latestversion->downloadUrl }}}" target="_blank" class="font-monospace small text-decoration-none text-secondary">
            {{{ $this->latestversion->downloadUrl }}}
        </a>
    </p>
    <p>
        <a href="{{{ $this->latestversion->infoUrl }}}" target="_blank">@lang('PANOPTICON_SELFUPDATE_LBL_INFORMATION')</a>
        <span class="fa fa-external-link text-muted small" aria-hidden="true"></span>
        &bullet;
        <a href="#releaseNotes"
           data-bs-toggle="collapse" data-bs-target="#releaseNotes" aria-expanded="false"
           aria-controls="releaseNotes"
        >
            @lang('PANOPTICON_SELFUPDATE_LBL_RELEASE_NOTES')
        </a>
    </p>
    <div id="releaseNotes" class="m-2 px-3 py-2 bg-body border border-info rounded-3 collapse">
        {{ (new \League\CommonMark\GithubFlavoredMarkdownConverter([]))->convert($this->latestversion->releaseNotes) }}
    </div>
</div>