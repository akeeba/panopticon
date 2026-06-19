<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Contracts;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;

/**
 * Contract for an MCP tool.
 *
 * A tool advertises its metadata (name, description, input schema) and the access controls it requires (an optional
 * API scope, and whether it is restricted to Super Users). The actual work is performed by a public `__invoke()`
 * method whose named parameters mirror the tool's input schema; that method is not part of this interface because
 * its signature varies from tool to tool.
 *
 * @since  2.2.0
 */
interface McpToolInterface
{
	/**
	 * The unique, machine-readable tool name (snake_case), e.g. `list_sites`.
	 *
	 * @return  string
	 * @since   2.2.0
	 */
	public function getName(): string;

	/**
	 * A human-readable description shown to the AI agent.
	 *
	 * @return  string
	 * @since   2.2.0
	 */
	public function getDescription(): string;

	/**
	 * The JSON Schema (as a PHP array) describing this tool's input arguments.
	 *
	 * @return  array
	 * @since   2.2.0
	 */
	public function getInputSchema(): array;

	/**
	 * The API scope this tool requires, mirroring the equivalent API endpoint, or NULL when no specific scope is
	 * required. A tool is only exposed when the authenticating API token grants this scope.
	 *
	 * @return  ApiScope|null
	 * @since   2.2.0
	 */
	public function getRequiredScope(): ?ApiScope;

	/**
	 * Whether this tool is restricted to Super Users only.
	 *
	 * @return  bool
	 * @since   2.2.0
	 */
	public function isSuperUserOnly(): bool;
}
