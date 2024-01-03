<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Version\Version;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */

$version       = Version::create(AKEEBA_PANOPTICON_VERSION);
?>

<div class="mt-3 mb-5 mx-2 py-4 px-2 bg-success border-success rounded-3 text-center text-white">
    <div class="display-1">
        <span class="fa fa-check-circle" aria-hidden="true"></span>
    </div>
    <h3 class="display-3">
        @lang('PANOPTICON_SELFUPDATE_LBL_UPTODATE_HEAD')
    </h3>
</div>

<p class="text-center fs-5">
	@lang('PANOPTICON_APP_TITLE') {{ AKEEBA_PANOPTICON_VERSION }}
	<span class="text-body-tertiary">({{ AKEEBA_PANOPTICON_CODENAME }})</span>
</p>
<p class="text-center fs-5">
    @sprintf('PANOPTICON_SELFUPDATE_LBL_UPTODATE_RELEASED', $this->getContainer()->html->basic->date(AKEEBA_PANOPTICON_DATE, $this->getLanguage()->text('DATE_FORMAT_LC1')))
</p>

<div class="mt-5 mb-3 d-flex flex-row justify-content-center align-items-center gap-3">
    <a class="btn btn-lg btn-outline-secondary" role="button"
       href="@route('index.php?view=selfupdate&force=1')">
        <span class="fa fa-fw fa-refresh" aria-hidden="true"></span>
        @lang('PANOPTICON_SELFUPDATE_BTN_RELOAD')
    </a>

    <a class="btn btn-sm btn-outline-warning" role="button"
       href="@route(sprintf('index.php?view=selfupdate&task=postinstall&%s=1', $this->getContainer()->session->getCsrfToken()->getValue()))">
        <span class="fa fa-fw fa-code" aria-hidden="true"></span>
        @lang('PANOPTICON_SELFUPDATE_BTN_RUN_POSTUPGRADE')
    </a>
</div>

@if (is_int($this->updateInformation->lastCheckTimestamp))
    <div class="my-3 d-flex flex-row justify-content-center small text-body-tertiary">
        @sprintf(
            'PANOPTICON_SELFUPDATE_LBL_LAST_CHECK_AND_VERSION',
            $this->getContainer()->html->basic->date('@' . $this->updateInformation->lastCheckTimestamp, $this->getLanguage()->text('DATE_FORMAT_LC7')),
            $this->latestversion->version
           )
    </div>
@endif