<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api\V1\Site;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\AbstractApiHandler;
use Akeeba\Panopticon\Model\Site;
use Awf\Registry\Registry;

/**
 * API handler for PUT /v1/site — create a new site.
 *
 * @since  1.4.0
 */
class Add extends AbstractApiHandler
{
	public function handle(): void
	{
		// Require super user or addown privilege
		$user = $this->container->userManager->getUser();

		if (!$user->getPrivilege('panopticon.super') && !$user->getPrivilege('panopticon.addown'))
		{
			$this->sendJsonError(403, 'You do not have permission to add sites.');
		}

		$body = $this->getJsonBody();

		if (empty($body['name']) || empty($body['url']))
		{
			$this->sendJsonError(400, 'The name and url fields are required.');
		}

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');

		$site->name    = $body['name'];
		$site->url     = $body['url'];
		$site->enabled = isset($body['enabled']) ? (int) (bool) $body['enabled'] : 1;
		$site->notes   = $body['notes'] ?? null;

		// Build the site configuration
		$config = new Registry();

		if (isset($body['config']) && is_array($body['config']))
		{
			$config->loadArray($body['config']);
		}

		// Set groups if provided
		if (isset($body['groups']) && is_array($body['groups']))
		{
			$config->set('config.groups', array_map('intval', $body['groups']));
		}

		$site->config = $config->toString();

		try
		{
			$site->save();
		}
		catch (\Throwable $e)
		{
			$this->sendJsonError(500, 'Failed to create site: ' . $e->getMessage());
		}

		$this->sendJsonResponse(
			[
				'id'      => $site->getId(),
				'name'    => $site->name,
				'url'     => $site->url,
				'enabled' => (bool) $site->enabled,
				'cmsType' => $site->cmsType()->value,
				'config'  => $site->getConfig()->toObject(),
			],
			201,
			'Site created successfully.'
		);
	}
}
