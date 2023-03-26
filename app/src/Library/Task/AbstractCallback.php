<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;

abstract class AbstractCallback implements CallbackInterface
{
	protected readonly string $name;

	public function __construct(protected readonly Container $container)
	{
	}

	final public function getTaskType(): string
	{
		return $this->name;
	}
}