<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\About\Html $this */

?>

<div class="card card-body text-center my-4 bg-light-subtle">
    <p class="display-1">
        @lang('PANOPTICON_APP_TITLE')
    </p>
    <p class="display-5 text-secondary">
        {{ AKEEBA_PANOPTICON_VERSION }} <span class="text-body-tertiary">{{ AKEEBA_PANOPTICON_CODENAME }}</span>
    </p>
</div>


@include('About/default_license')

@include('About/default_contributors')

@include('About/default_dependencies')