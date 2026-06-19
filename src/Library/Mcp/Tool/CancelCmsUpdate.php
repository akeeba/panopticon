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
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\AuditLog;

/**
 * MCP tool: cancel a scheduled CMS update for a site.
 *
 * Mirrors `POST /api/v1/site/:id/cmsupdate/cancel` (scope `sites:cms-update`, per-site `panopticon.run`).
 *
 * @since  2.2.0
 */
class CancelCmsUpdate extends AbstractTool
{
	public function getName(): string
	{
		return 'cancel_cms_update';
	}

	public function getDescription(): string
	{
		return 'Cancel a previously scheduled CMS core update for a site. Fails if the update is already running.';
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
		$site    = $this->getSiteWithPermission($id, 'run');
		$user    = $this->getUser();
		$cmsType = $site->cmsType();

		if ($cmsType !== CMSType::JOOMLA && $cmsType !== CMSType::WORDPRESS)
		{
			throw new \RuntimeException('Unsupported CMS type.');
		}

		$task = $cmsType === CMSType::JOOMLA
			? $site->getJoomlaUpdateTask()
			: $site->getWordPressUpdateTask();

		if ($task === null)
		{
			throw new \RuntimeException('No scheduled CMS update task found for this site.');
		}

		if (in_array((int) $task->last_exit_code, [Status::WILL_RESUME->value, Status::RUNNING->value], true))
		{
			throw new \RuntimeException('The CMS update is currently running and cannot be cancelled.');
		}

		try
		{
			$task->last_exit_code = Status::OK->value;
			$task->unpublish();
		}
		catch (\Throwable $e)
		{
			throw new \RuntimeException('Failed to cancel CMS update: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.cmsupdate.cancel',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value]
		);

		return [
			'success' => true,
			'message' => 'CMS update cancelled successfully.',
		];
	}
}
