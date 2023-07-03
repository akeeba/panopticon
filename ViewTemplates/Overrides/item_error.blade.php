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
        Error retrieving the template override from the site
    </h3>
    <p>
        The template override, or the corresponding original file, may no longer exist on your site.
    </p>
    <p>
        Please visit your site's administrator backend to inspect the template overrides.
    </p>
</div>