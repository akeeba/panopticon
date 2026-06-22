<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Mcp\Contracts\McpToolInterface;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Model\Site;

/**
 * Base class for MCP tools.
 *
 * Provides the Panopticon container and a set of ACL helpers that mirror those of the API handlers
 * ({@see \Akeeba\Panopticon\Controller\Api\AbstractApiHandler}) — except that, instead of writing a JSON response and
 * terminating the request, these helpers throw exceptions. The MCP dispatcher catches those exceptions and reports
 * them back to the AI agent as a tool error.
 *
 * This guarantees **permission parity** with the API: a tool can never see or do more than the same user could through
 * the equivalent API endpoint using the same token.
 *
 * @since  2.2.0
 */
abstract class AbstractTool implements McpToolInterface
{
	/**
	 * @param   Container  $container  The Panopticon container, with the authenticated user already set.
	 * @since   2.2.0
	 */
	public function __construct(protected readonly Container $container) {}

	/**
	 * @inheritDoc
	 * @since   2.2.0
	 */
	public function getRequiredScope(): ?ApiScope
	{
		return null;
	}

	/**
	 * @inheritDoc
	 * @since   2.2.0
	 */
	public function isSuperUserOnly(): bool
	{
		return false;
	}

	/**
	 * Get the currently authenticated user.
	 *
	 * @return  User
	 * @since   2.2.0
	 */
	protected function getUser(): User
	{
		return $this->container->userManager->getUser();
	}

	/**
	 * Throw if the current user is not a Super User.
	 *
	 * @return  void
	 * @since   2.2.0
	 */
	protected function assertSuperUser(): void
	{
		if (!$this->getUser()->getPrivilege('panopticon.super'))
		{
			throw new \RuntimeException('This tool requires Super User privileges.');
		}
	}

	/**
	 * Return the current client's IP as a packed (inet_pton) binary string, or NULL.
	 *
	 * @return  string|null
	 * @since   2.2.0
	 */
	protected function getClientIpBinary(): ?string
	{
		$ip = $_SERVER['REMOTE_ADDR'] ?? null;

		if (empty($ip))
		{
			return null;
		}

		$packed = @inet_pton((string) $ip);

		return $packed === false ? null : $packed;
	}

	/**
	 * Load a site by ID, enforcing the same per-site ACL as the API.
	 *
	 * @param   int     $id         The site ID.
	 * @param   string  $privilege  The required privilege (e.g. 'read', 'run', 'admin').
	 *
	 * @return  Site
	 * @since   2.2.0
	 */
	protected function getSiteWithPermission(int $id, string $privilege): Site
	{
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');

		try
		{
			$site->findOrFail($id);
		}
		catch (\Throwable)
		{
			throw new \RuntimeException(sprintf('Site %d was not found.', $id));
		}

		$user = $this->getUser();

		if (!$user->getPrivilege('panopticon.super') && !$user->authorise('panopticon.' . $privilege, $id))
		{
			throw new \RuntimeException(sprintf('You do not have permission to access site %d.', $id));
		}

		return $site;
	}
}
