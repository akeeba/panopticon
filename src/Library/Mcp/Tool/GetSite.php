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

/**
 * MCP tool: get details for a single site.
 *
 * Mirrors `GET /api/v1/site/:id` with the same per-site ACL (`panopticon.read`).
 *
 * Unlike the JSON API — which dumps the full site configuration verbatim, including stored secrets such as download
 * keys and connection credentials — this tool deliberately returns only a curated, **non-secret** summary. Exposing
 * credentials inside an AI agent's context is undesirable, and the MCP requirement is permission parity (which sites a
 * user may see), not byte-for-byte payload parity. The set of sites visible here is identical to the API.
 *
 * @since  2.2.0
 */
class GetSite extends AbstractTool
{
	public function getName(): string
	{
		return 'get_site';
	}

	public function getDescription(): string
	{
		return 'Get details for a single monitored site by its numeric ID: name, URL, CMS type and version, '
			. 'available updates, PHP version, and notes.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SitesRead;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => 'The numeric ID of the site.',
				],
			],
			'required'   => ['id'],
		];
	}

	public function __invoke(int $id): array
	{
		$site   = $this->getSiteWithPermission($id, 'read');
		$config = $site->getConfig();

		return [
			'id'                 => (int) $site->getId(),
			'name'               => $site->name,
			'url'                => $site->getBaseUrl(),
			'enabled'            => (bool) $site->enabled,
			'cmsType'            => $site->cmsType()->value,
			'cms_version'        => $config->get('core.current.version'),
			'cms_latest_version' => $config->get('core.latest.version'),
			'cms_update_available' => (bool) $config->get('core.canUpgrade', false),
			'php_version'        => $config->get('core.php'),
			'notes'              => $site->notes,
		];
	}
}
