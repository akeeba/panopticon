<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Tool;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Mcp\AbstractTool;
use Akeeba\Panopticon\Model\Site;

/**
 * MCP tool: list the sites the authenticated user can access.
 *
 * Mirrors `GET /api/v1/sites`. The underlying Site model automatically restricts the result set to the sites the
 * current user is allowed to see, so a user can never even learn that sites they cannot access exist.
 *
 * @since  2.2.0
 */
class ListSites extends AbstractTool
{
	public function getName(): string
	{
		return 'list_sites';
	}

	public function getDescription(): string
	{
		return 'List the monitored Joomla and WordPress sites the current user can access, with optional search and '
			. 'filtering. Returns id, name, URL, enabled flag and CMS type for each site.';
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
				'search'  => [
					'type'        => 'string',
					'description' => 'Filter sites whose name or URL contains this text.',
				],
				'enabled' => [
					'type'        => 'boolean',
					'description' => 'Only return enabled (true) or disabled (false) sites.',
				],
				'cmsType' => [
					'type'        => 'string',
					'enum'        => array_values(
						array_filter(array_map(fn(CMSType $t) => $t->value, CMSType::cases()))
					),
					'description' => 'Only return sites of this CMS type.',
				],
				'limit'   => [
					'type'        => 'integer',
					'description' => 'Maximum number of sites to return (default 50).',
				],
				'offset'  => [
					'type'        => 'integer',
					'description' => 'Number of sites to skip, for pagination (default 0).',
				],
			],
		];
	}

	public function __invoke(
		?string $search = null,
		?bool $enabled = null,
		?string $cmsType = null,
		int $limit = 50,
		int $offset = 0
	): array
	{
		/** @var Site $model */
		$model = $this->container->mvcFactory->makeTempModel('Site');

		if ($search !== null && $search !== '')
		{
			$model->setState('search', $search);
		}

		if ($enabled !== null)
		{
			$model->setState('enabled', $enabled ? 1 : 0);
		}

		if ($cmsType !== null && $cmsType !== '')
		{
			if (CMSType::tryFrom($cmsType) === null)
			{
				throw new \RuntimeException(sprintf('Invalid cmsType: %s', $cmsType));
			}

			$model->setState('cmsType', $cmsType);
		}

		$limit  = max(0, min(200, $limit));
		$offset = max(0, $offset);

		$model->setState('limitstart', $offset);
		$model->setState('limit', $limit);

		$items = $model->get(true);
		$total = $model->count();

		$sites = [];

		/** @var Site $item */
		foreach ($items as $item)
		{
			$sites[] = [
				'id'      => (int) $item->getId(),
				'name'    => $item->name,
				'url'     => $item->getBaseUrl(),
				'enabled' => (bool) $item->enabled,
				'cmsType' => $item->cmsType()->value,
			];
		}

		return [
			'sites'      => $sites,
			'pagination' => [
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			],
		];
	}
}
