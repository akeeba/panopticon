<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Selfupdate\Html $this */
?>
@if ($this->updateInformation->stuck)
    @include('Selfupdate/default_stuck')
@elseif (!$this->updateInformation->loadedUpdate)
    @include('Selfupdate/default_not_loaded')
@elseif (!$this->hasUpdate)
    @include('Selfupdate/default_noupdate')
@else
    @include('Selfupdate/default_update')
@endif