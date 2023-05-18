<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\CliCommand\Attribute;

defined('AKEEBA') || die;

use Attribute;

#[Attribute(\Attribute::TARGET_CLASS)]
class AppHeader
{
	public function __construct(protected bool $showHeader = true)
	{
	}
}