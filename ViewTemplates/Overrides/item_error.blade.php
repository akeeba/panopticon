<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Overrides\Html $this
 */
?>

<div class="alert alert-danger">
    <h3 class="alert-heading">
        @lang('PANOPTICON_OVERRIDES_LBL_NO_OVERRIDE_HEAD')
    </h3>
    <p>
        @lang('PANOPTICON_OVERRIDES_LBL_NO_OVERRIDE_MESSAGE')
    </p>
    <p>
        @lang('PANOPTICON_OVERRIDES_LBL_NO_OVERRIDE_SUGGESTION')
    </p>
</div>