<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Tool;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Mcp\AbstractTool;
use Akeeba\Panopticon\Model\AuditLog;

/**
 * MCP tool: refresh a site's information.
 *
 * Mirrors `POST /api/v1/site/:id/refresh` (scope `sites:refresh`, per-site `panopticon.read`). Synchronously
 * re-collects the site's update, extension and health information.
 *
 * @since  2.2.0
 */
class RefreshSite extends AbstractTool
{
	public function getName(): string
	{
		return 'refresh_site';
	}

	public function getDescription(): string
	{
		return 'Refresh a site\'s information now: re-check its CMS and extension updates and overall status. Use this '
			. 'when you need up-to-date information about a site before reporting on it.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SitesRefresh;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => 'The numeric ID of the site to refresh.',
				],
			],
			'required'   => ['id'],
		];
	}

	public function __invoke(int $id): array
	{
		$site = $this->getSiteWithPermission($id, 'read');
		$user = $this->getUser();

		try
		{
			$site->doRefreshSiteInformation();
		}
		catch (\Throwable $e)
		{
			throw new \RuntimeException('Failed to refresh site information: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.refresh',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId()
		);

		return [
			'success' => true,
			'message' => 'Site information refreshed successfully.',
		];
	}
}
