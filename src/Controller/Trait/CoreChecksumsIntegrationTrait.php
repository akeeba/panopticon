<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;

/**
 * Trait for core file integrity checksum integration in the Sites controller.
 *
 * @since  1.3.0
 */
trait CoreChecksumsIntegrationTrait
{
	/**
	 * Enqueue a core checksums check for a site.
	 *
	 * @return  bool
	 * @since   1.3.0
	 */
	public function coreChecksumsEnqueue(): bool
	{
		$this->csrfProtection();

		$id = $this->input->getInt('id', null);

		if (empty($id) || $id <= 0)
		{
			return false;
		}

		/** @var Site $model */
		$model = $this->getModel();
		$user  = $this->container->userManager->getUser();

		$model->findOrFail($id);

		$canEditMine = $user->getId() == $model->created_by && $user->getPrivilege('panopticon.editown');

		if (
			!$user->authorise('panopticon.run', $model)
			&& !$user->authorise('panopticon.admin', $model)
			&& !$canEditMine
		)
		{
			return false;
		}

		$defaultRedirect = $this->container->router->route(sprintf('index.php?view=site&task=read&id=%d', $id));

		try
		{
			$model->coreChecksumsScanEnqueue($this->getContainer()->userManager->getUser());

			// Redirect
			$this->setRedirectWithMessage($defaultRedirect);
		}
		catch (\Exception $e)
		{
			$this->setRedirectWithMessage($defaultRedirect, $e->getMessage(), 'error');
		}

		return true;
	}
}
