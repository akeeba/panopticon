<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

?>
@if(empty($this->phpInfo))
    <h3 class="display-6 text-center bg-light p-2 fw-bold">
        <span class="fab fa-php me-2" aria-hidden="true"></span>
        @sprintf('PANOPTICON_SELFUPDATE_LBL_PHP_VERSION', PHP_VERSION)
    </h3>
    <div class="alert alert-danger">
        <h3 class="alert-heading">
            @lang('PANOPTICON_SELFUPDATE_LBL_PHPINFO_NOT_AVAILABLE')
        </h3>
        <p>
            @lang('PANOPTICON_SELFUPDATE_LBL_PHPINFO_NOT_AVAILABLE_INFO')
        </p>
    </div>
@else
    {{ $this->phpInfo }}
@endif