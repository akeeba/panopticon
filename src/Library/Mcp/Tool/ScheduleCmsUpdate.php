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
use Akeeba\Panopticon\Model\AuditLog;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\EnqueueJoomlaUpdateTrait;
use Akeeba\Panopticon\Task\Trait\EnqueueWordPressUpdateTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;

/**
 * MCP tool: schedule a CMS (Joomla/WordPress) core update for a site.
 *
 * Mirrors `POST /api/v1/site/:id/cmsupdate` (scope `sites:cms-update`, per-site `panopticon.run`).
 *
 * @since  2.2.0
 */
class ScheduleCmsUpdate extends AbstractTool
{
	use EnqueueJoomlaUpdateTrait;
	use EnqueueWordPressUpdateTrait;
	use SaveSiteTrait;

	public function getName(): string
	{
		return 'schedule_cms_update';
	}

	public function getDescription(): string
	{
		return 'Schedule a CMS core update (Joomla or WordPress) for a site. The update runs in the background on the '
			. 'next task run. Returns once the update has been queued.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SitesCmsUpdate;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'site_id' => [
					'type'        => 'integer',
					'description' => 'The numeric ID of the site to update.',
				],
				'id'      => [
					'type'        => 'integer',
					'description' => 'Alias for site_id. The numeric ID of the site to update.',
				],
				'force'   => [
					'type'        => 'boolean',
					'description' => 'Force scheduling even if no update appears to be available (default false).',
				],
			],
			'required'   => ['site_id'],
		];
	}

	public function __invoke(int $site_id = 0, int $id = 0, bool $force = false): array
	{
		$site    = $this->getSiteWithPermission($site_id ?: $id, 'run');
		$user    = $this->getUser();
		$cmsType = $site->cmsType();

		if ($cmsType !== CMSType::JOOMLA && $cmsType !== CMSType::WORDPRESS)
		{
			throw new \RuntimeException('Unsupported CMS type for update scheduling.');
		}

		try
		{
			if ($cmsType === CMSType::JOOMLA)
			{
				$this->enqueueJoomlaUpdate($site, $this->container, $force, $user);
			}
			else
			{
				$this->enqueueWordPressUpdate($site, $this->container, $force, $user);
			}

			$this->saveSite(
				$site,
				function (Site $site): void
				{
					$config = $site->getConfig();
					$config->set('core.lastAutoUpdateVersion', $config->get('core.latest.version'));
					$site->config = $config->toString();
				}
			);
		}
		catch (\Throwable $e)
		{
			throw new \RuntimeException('Failed to schedule CMS update: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.cmsupdate.schedule',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['force' => $force, 'cmsType' => $cmsType->value]
		);

		return [
			'success' => true,
			'message' => 'CMS update scheduled successfully.',
		];
	}
}
