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
use Akeeba\Panopticon\Library\Queue\QueueInterface;
use Akeeba\Panopticon\Library\Queue\QueueTypeEnum;
use Akeeba\Panopticon\Model\AuditLog;

/**
 * MCP tool: cancel a queued extension/plugin update on a site.
 *
 * Mirrors `POST /api/v1/site/:id/extensions/cancel/:extId` (scope `sites:extensions`, per-site `panopticon.run`).
 *
 * @since  2.2.0
 */
class CancelExtensionUpdate extends AbstractTool
{
	public function getName(): string
	{
		return 'cancel_extension_update';
	}

	public function getDescription(): string
	{
		return 'Remove a single extension (Joomla) or plugin (WordPress) from a site\'s update queue, cancelling its '
			. 'scheduled update.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SitesExtensions;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => [
				'site_id'      => [
					'type'        => 'integer',
					'description' => 'The numeric ID of the site.',
				],
				'id'           => [
					'type'        => 'integer',
					'description' => 'Alias for site_id. The numeric ID of the site.',
				],
				'extension_id' => [
					'type'        => 'string',
					'description' => 'The ID of the extension/plugin whose queued update should be cancelled, exactly as '
						. 'returned by list_site_extensions.',
				],
			],
			'required'   => ['site_id', 'extension_id'],
		];
	}

	public function __invoke(int $site_id = 0, int $id = 0, string $extension_id = ''): array
	{
		$site = $this->getSiteWithPermission($site_id ?: $id, 'run');
		$user = $this->getUser();

		if (empty($extension_id))
		{
			throw new \RuntimeException('Invalid extension ID.');
		}

		$cmsType = $site->cmsType();

		$queuePattern = match ($cmsType)
		{
			CMSType::JOOMLA    => QueueTypeEnum::EXTENSIONS->value,
			CMSType::WORDPRESS => QueueTypeEnum::PLUGINS->value,
			default            => null,
		};

		if ($queuePattern === null)
		{
			throw new \RuntimeException('Unsupported CMS type for extension updates.');
		}

		$queueKey = sprintf($queuePattern, $site->getId());

		try
		{
			/** @var QueueInterface $queue */
			$queue    = $this->container->queueFactory->makeQueue($queueKey);
			$existing = $queue->countByCondition(['data.id' => $extension_id, 'siteId' => $site->getId()]);
		}
		catch (\Throwable $e)
		{
			throw new \RuntimeException('Failed to inspect update queue: ' . $e->getMessage());
		}

		if ($existing === 0)
		{
			throw new \RuntimeException('Extension is not in the update queue.');
		}

		try
		{
			$queue->clear(['data.id' => $extension_id, 'siteId' => $site->getId()]);
		}
		catch (\Throwable $e)
		{
			throw new \RuntimeException('Failed to cancel extension update: ' . $e->getMessage());
		}

		AuditLog::record(
			'site.extension.cancelupdate',
			(int) $user->getId() ?: null,
			$this->getClientIpBinary(),
			'site',
			(int) $site->getId(),
			['cmsType' => $cmsType->value, 'extensionId' => $extension_id]
		);

		return [
			'success' => true,
			'message' => 'Extension update cancelled successfully.',
		];
	}
}
