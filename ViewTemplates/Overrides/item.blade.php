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

<h3 class="text-body-secondary border-bottom border-2 border-info-subtle">
    <span class="text-body-tertiary me-2">#{{ (int) $this->site->id }}</span>
    {{ $this->site->name }}
</h3>

@if($this->item instanceof Throwable || empty($this->item))
    @include('Overrides/item_error')
@else
    @include('Overrides/item_ui')
@endif
