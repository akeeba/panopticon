<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp;

defined('AKEEBA') || die;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * A tiny PSR-11 container that resolves MCP tool handlers.
 *
 * `php-mcp/server` resolves array handlers of the form `[ClassName::class, '__invoke']` by calling
 * `$container->get(ClassName::class)`. This container is seeded with the already-instantiated tool objects
 * (each constructed with Panopticon's own container injected) and returns them by class name.
 *
 * @since  2.2.0
 */
class ToolContainer implements ContainerInterface
{
	/**
	 * @param   array<class-string, object>  $instances  Map of tool class name to its instance.
	 * @since   2.2.0
	 */
	public function __construct(private readonly array $instances) {}

	/**
	 * @inheritDoc
	 * @since   2.2.0
	 */
	public function get(string $id): object
	{
		if (!isset($this->instances[$id]))
		{
			throw new class("MCP tool handler '$id' is not registered.") extends \RuntimeException implements NotFoundExceptionInterface {};
		}

		return $this->instances[$id];
	}

	/**
	 * @inheritDoc
	 * @since   2.2.0
	 */
	public function has(string $id): bool
	{
		return isset($this->instances[$id]);
	}
}
