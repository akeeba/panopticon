<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;


use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\EnqueueExtensionUpdateTrait;
use Akeeba\Panopticon\Task\Trait\EnqueuePluginUpdateTrait;
use Awf\Mvc\Controller;
use Awf\Uri\Uri;

defined('AKEEBA') || die;

class Extupdates extends Controller
{
	use EnqueueExtensionUpdateTrait;
	use EnqueuePluginUpdateTrait;
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function main()
	{
		$view      = $this->getView();
		$siteModel = $this->getModel('site');
		$view->setModel('site', $siteModel);

		// When no group filter is selected we are POSTed no value. In this case, we need to unset the filter.
		if (strtoupper($this->input->getMethod() ?? '') === 'POST')
		{
			$groups = $this->input->post->getRaw('group');

			if ($groups === null)
			{
				$this->input->set('group', []);
			}
		}

		parent::main();
	}

	public function update()
	{
		// Anti-CSRF token check
		$this->csrfProtection();

		// Get the extension IDs from the request
		$extensionIDs = $this->input->get('eid', [], 'array');

		// Make sure I got an array
		if (!is_array($extensionIDs))
		{
			$extensionIDs = [];
		}

		// The data I get is in the form `siteId_extensionId`. Map and filter it.
		$extensionIDs = array_map(
			function ($eid): ?array {
				if (empty($eid) || !is_string($eid) || !str_contains($eid, '_'))
				{
					return null;
				}

				[$siteId, $extensionId] = explode('_', $eid, 2);

				if (!preg_match('#^\d+$#', $siteId))
				{
					return null;
				}

				return [$siteId, $extensionId];
			},
			$extensionIDs
		);

		$extensionIDs = array_filter($extensionIDs);

		// Prepare the return URL before doing anything else
		$returnUri = $this->input->get->getBase64('return', '');

		if (!empty($returnUri))
		{
			$returnUri = @base64_decode((string) $returnUri);

			if (!Uri::isInternal($returnUri))
			{
				$returnUri = null;
			}
		}

		$returnUri = $returnUri ?: $this->getContainer()->router->route('index.php?view=extupdates');

		// If I do not have any extensions left, redirect with an error
		if (empty($extensionIDs))
		{
			$this->setRedirect(
				$returnUri, $this->getLanguage()->text('PANOPTICON_EXTUPDATES_ERR_INVALID_SELECTION'), 'error'
			);
		}

		// Schedule the updates
		$numScheduled = 0;
		$user         = $this->container->userManager->getUser();

		foreach ($extensionIDs as $info)
		{
			[$siteId, $extensionId] = $info;

			/** @var Site $site */
			$site = clone $this->getModel('site');

			try
			{
				$site->findOrFail($siteId);
			}
			catch (\Exception)
			{
				continue;
			}

			// You can only schedule updates if you have the admin or editown privilege on the site
			$haveGlobalPrivilege = $user->authorise('panopticon.admin', $site);
			$canEditOwn          = $user->authorise('panopticon.editown', $site) && $site->created_by == $user->getId();

			if (!$haveGlobalPrivilege && !$canEditOwn)
			{
				continue;
			}

			// Enqueue the update
			if ($site->cmsType() === CMSType::JOOMLA)
			{
				if ($this->enqueueExtensionUpdate($site, $extensionId, 'major', $user))
				{
					$this->scheduleExtensionsUpdateForSite($site, $this->getContainer());

					$numScheduled++;
				}
			}
			elseif ($site->cmsType() === CMSType::WORDPRESS)
			{
				if ($this->enqueuePluginUpdate($site, $extensionId, 'major', $user))
				{
					$this->schedulePluginsUpdateForSite($site, $this->getContainer());

					$numScheduled++;
				}
			}
		}

		$message     = $this->getLanguage()->plural('PANOPTICON_EXTUPDATES_LBL_SCHEDULED_N', $numScheduled);
		$messageType = $numScheduled ? 'success' : 'warning';

		$this->setRedirect($returnUri, $message, $messageType);
	}
}