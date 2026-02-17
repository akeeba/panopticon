<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Site;
use Awf\Registry\Registry;

/**
 * API handler for POST /v1/site/:id — update an existing site.
 *
 * @since  1.4.0
 */
class Modify extends AbstractApiHandler
{
	public function handle(): void
	{
		$id   = $this->input->getInt('id', 0);
		$user = $this->container->userManager->getUser();

		// Check admin privilege, or editown if the user owns the site
		$site = $this->getSiteWithPermission($id, 'admin');

		// If the admin check failed (sendJsonError terminates), we won't reach here.
		// But we also want to allow editown for site owners — re-check more permissively.
		if (
			!$user->getPrivilege('panopticon.super')
			&& !$user->authorise('panopticon.admin', $id)
		)
		{
			$isOwner = ((int) $site->created_by === (int) $user->getId());

			if (!$isOwner || !$user->authorise('panopticon.editown', $id))
			{
				$this->sendJsonError(403, 'You do not have permission to modify this site.');
			}
		}

		$body = $this->getJsonBody();

		if (empty($body))
		{
			$this->sendJsonError(400, 'Request body is empty.');
		}

		// Use table locking pattern to safely update the site
		$db = Factory::getContainer()->db;
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__sites');

		try
		{
			// Reload the site to get the freshest data
			$tempSite = $site->getClone()->reset(true, true)->findOrFail($site->getId());

			if (array_key_exists('name', $body))
			{
				$tempSite->name = $body['name'];
			}

			if (array_key_exists('url', $body))
			{
				$tempSite->url = $body['url'];
			}

			if (array_key_exists('enabled', $body))
			{
				$tempSite->enabled = (int) (bool) $body['enabled'];
			}

			if (array_key_exists('notes', $body))
			{
				$tempSite->notes = $body['notes'];
			}

			if (array_key_exists('config', $body) && is_array($body['config']))
			{
				$config = $tempSite->getConfig();

				foreach ($body['config'] as $key => $value)
				{
					$config->set($key, $value);
				}

				$tempSite->config = $config->toString();
			}

			if (array_key_exists('groups', $body) && is_array($body['groups']))
			{
				$config = $tempSite->getConfig();
				$config->set('config.groups', array_map('intval', $body['groups']));
				$tempSite->config = $config->toString();
			}

			$tempSite->save();
			$site->bind($tempSite->getData());
		}
		catch (\Throwable $e)
		{
			$db->setQuery('COMMIT')->execute();
			$db->unlockTables();

			$this->sendJsonError(500, 'Failed to update site: ' . $e->getMessage());
		}

		$db->setQuery('COMMIT')->execute();
		$db->unlockTables();

		$this->sendJsonResponse(
			[
				'id'      => $site->getId(),
				'name'    => $site->name,
				'url'     => $site->url,
				'enabled' => (bool) $site->enabled,
				'cmsType' => $site->cmsType()->value,
				'config'  => $site->getConfig()->toObject(),
			],
			200,
			'Site updated successfully.'
		);
	}
}
