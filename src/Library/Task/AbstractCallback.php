<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;

abstract class AbstractCallback implements CallbackInterface
{
	protected readonly string $name;

	protected readonly string $description;

	public function __construct(protected readonly Container $container)
	{
		$refObj     = new \ReflectionObject($this);
		$attributes = $refObj->getAttributes(AsTask::class);

		if (!empty($attributes))
		{
			/** @var AsTask $attribute */
			$attribute         = $attributes[0]->newInstance();
			$this->name        = $attribute->getName();
			$this->description = $attribute->getDescription();
		}
	}

	final public function getTaskType(): string
	{
		return $this->name;
	}

	final public function getDescription(): string
	{
		return $this->description;
	}
}