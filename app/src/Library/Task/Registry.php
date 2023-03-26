<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Task;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Exception\InvalidTaskType;
use DirectoryIterator;
use DomainException;
use Throwable;

defined('AKEEBA') || die;

class Registry
{
	public function __construct(private readonly Container $container, private array $registry = [], private readonly bool $autoPopulate = true)
	{
	}

	public function add(string $type, CallbackInterface $callback): void
	{
		if (empty($type))
		{
			throw new DomainException('Adding task types to the Task Registry requires a non-empty task type', 500);
		}

		$this->registry[strtolower($type)] = $callback;
	}

	public function remove(string $type): void
	{
		if ($this->autoPopulate && empty($this->registry))
		{
			$this->populate();
		}

		if (empty($type) || !isset($this->registry[strtolower($type)]))
		{
			return;
		}

		unset($this->registry[strtolower($type)]);
	}

	public function has(string $type): bool
	{
		if ($this->autoPopulate && empty($this->registry))
		{
			$this->populate();
		}

		return !empty($type) && isset($this->registry[strtolower($type)]);
	}

	public function get(string $type): CallbackInterface
	{
		if ($this->autoPopulate && empty($this->registry))
		{
			$this->populate();
		}

		if (empty($type) || !isset($this->registry[strtolower($type)]))
		{
			throw new InvalidTaskType($type);
		}

		return $this->registry[strtolower($type)];
	}

	public function addFromClassname(string $classname): void
	{
		if (empty($classname))
		{
			throw new DomainException('Adding task types to the Task Registry by class name requires a non-empty class name', 500);
		}

		if (!class_exists($classname)) {
			throw new DomainException(
				sprintf('Cannot add non-existent class ‘%s’ to the Task Registry.', $classname),
				500
			);
		}

		try
		{
			$o = new $classname($this->container);
		}
		catch (Throwable $e)
		{
			throw new DomainException(
				sprintf('Error instantiating class ‘%s’: #%d -- %s', $classname, $e->getCode(), $e->getMessage()),
				500,
				$e
			);
		}

		if (!$o instanceof CallbackInterface)
		{
			throw new DomainException(
				sprintf('Class ‘%s’ does not implement %s.', $classname, CallbackInterface::class),
				500
			);
		}

		$this->add($o->getTaskType(), $o);
	}

	private function populate(): void
	{
		$di = new DirectoryIterator(APATH_ROOT . '/src/Task');

		foreach ($di as $file)
		{
			if (!$file->isFile() || $file->getExtension() != 'php')
			{
				continue;
			}

			$className = '\\Akeeba\\Panopticon\\Task\\' . $file->getBasename('.php');

			if (!class_exists($className))
			{
				continue;
			}

			$this->addFromClassname($className);
		}
	}
}