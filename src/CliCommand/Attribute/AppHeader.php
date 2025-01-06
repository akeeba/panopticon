<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
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