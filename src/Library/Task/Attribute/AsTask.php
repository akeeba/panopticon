<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Task\Attribute;

use Awf\Text\Text;

defined('AKEEBA') || die;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsTask
{
	public function __construct(private readonly string $name, private readonly string $description)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getDescription(): string
	{
		return Text::_($this->description);
	}
}