<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * Renders a single group badge.
 *
 * @var  string       $title   The group's title.
 * @var  string|null  $colour  The group's badge colour, or NULL for the default grey badge.
 */

$colour  = $this->container->helper->colour->sanitise($colour ?? null);
$fgClass = $colour ? $this->container->helper->colour->foregroundClass($colour) : '';
?>
<span class="badge {{ $colour ? $fgClass : 'bg-secondary' }}"
	@if ($colour) style="background-color: {{{ $colour }}}" @endif
>{{{ $title }}}</span>
