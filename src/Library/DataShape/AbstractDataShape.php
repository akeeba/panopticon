<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\DataShape;

defined('AKEEBA') || die;

use InvalidArgumentException;

class AbstractDataShape
{
	public function __construct($array = [])
	{
		if (!is_array($array) && !($array instanceof self))
		{
			throw new InvalidArgumentException(sprintf('%s needs an array or a %s object', __METHOD__, __CLASS__));
		}

		foreach (($array instanceof self) ? $array->asArray() : $array as $k => $v)
		{
			$this[$k] = $v;
		}
	}

	public function merge($newValues): self
	{
		if (!is_array($newValues) && !($newValues instanceof self))
		{
			throw new InvalidArgumentException(sprintf('%s needs an array or a %s object', __METHOD__, __CLASS__));
		}

		foreach (($newValues instanceof self) ? $newValues->asArray() : $newValues as $k => $v)
		{
			if (!isset($this->{$k}))
			{
				continue;
			}

			$this[$k] = $v;
		}

		return $this;
	}

	public function asArray(): array
	{
		return get_object_vars($this);
	}

	public function __get($name)
	{
		$methodName = 'get' . ucfirst($name);

		if (method_exists($this, $methodName))
		{
			return $this->{$methodName};
		}

		if (property_exists($this, $name))
		{
			return $this->{$name};
		}

		throw new InvalidArgumentException(sprintf('Property %s not found in %s', $name, __CLASS__));
	}

	public function __set($name, $value)
	{
		$methodName = 'set' . ucfirst($name);

		if (method_exists($this, $methodName))
		{
			return $this->{$methodName}($value);
		}

		if (property_exists($this, $name))
		{
			$this->{$name} = $value;
		}

		throw new InvalidArgumentException(sprintf('Property %s not found in %s', $name, __CLASS__));
	}

	public function __isset($name)
	{
		$methodName = 'get' . ucfirst($name);

		return method_exists($this, $methodName) || property_exists($this, $name);
	}

	public function offsetExists($offset)
	{
		return isset($this->{$offset});
	}

	public function offsetGet($offset)
	{
		return $this->{$offset};
	}

	public function offsetSet($offset, $value)
	{
		$this->{$offset} = $value;
	}

	public function offsetUnset($offset)
	{
		throw new \LogicException(sprintf('You cannot unset members of %s', __CLASS__));
	}
}