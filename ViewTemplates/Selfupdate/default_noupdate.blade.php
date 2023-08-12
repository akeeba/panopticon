<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Version\Version;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */

$version = Version::create(AKEEBA_PANOPTICON_VERSION);

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
    @sprintf('PANOPTICON_SELFUPDATE_LBL_UPTODATE_RELEASED', \Awf\Html\Html::date(AKEEBA_PANOPTICON_DATE, \Awf\Text\Text::_('DATE_FORMAT_LC1')))
</p>
