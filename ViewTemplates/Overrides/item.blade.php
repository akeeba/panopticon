<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Overrides\Html $this
 */

$favIcon = $this->site->getFavicon(asDataUrl: true, onlyIfCached: true);

?>

<h3 class="mt-2 pb-1 border-bottom border-3 border-primary-subtle d-flex flex-row align-items-center gap-2">
    <span class="text-muted fw-light fs-4">#{{ (int) $this->site->id }}</span>
    @if($favIcon)
        <img src="{{{ $favIcon }}}"
             style="max-width: 1em; max-height: 1em; aspect-ratio: 1.0"
             class="mx-1 p-1 border rounded"
             alt="">
    @endif
    <span class="flex-grow-1">{{{ $this->site->name }}}</span>
</h3>

@if($this->item instanceof Throwable || empty($this->item))
    @include('Overrides/item_error')
@else
    @include('Overrides/item_ui')
@endif
