<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Model\Users;
use Awf\Mvc\Controller;

class Userconsent extends Controller
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function agree(): void
	{
		$this->csrfProtection();

		$user = $this->container->userManager->getUser();

		if (!$user->getId())
		{
			$this->setRedirect($this->container->router->route('index.php?view=login'));

			return;
		}

		/** @var Users $model */
		$model = $this->container->mvcFactory->makeTempModel('Users');
		$model->setConsent($user->getId());

		$this->setRedirect(
			$this->container->router->route('index.php?view=main'),
			$this->getLanguage()->text('PANOPTICON_USERCONSENT_MSG_AGREED')
		);
	}

	public function decline(): void
	{
		$this->container->segment->setFlash(
			'userconsent.declined',
			$this->getLanguage()->text('PANOPTICON_USERCONSENT_MSG_DECLINED')
		);

		$this->container->userManager->logoutUser();
		$this->container->session->destroy();

		$this->setRedirect($this->container->router->route('index.php?view=login'));
	}

	public function export(): void
	{
		$user = $this->container->userManager->getUser();

		if (!$user->getId())
		{
			$this->setRedirect($this->container->router->route('index.php?view=login'));

			return;
		}

		/** @var Users $model */
		$model = $this->container->mvcFactory->makeTempModel('Users');
		$xml   = $model->exportUserDataXml($user->getId());

		$filename = sprintf('panopticon-userdata-%s-%s.xml', $user->getUsername(), date('Ymd-His'));

		@ob_end_clean();
		header('Content-Type: application/xml; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($xml));
		header('Cache-Control: no-cache, no-store, must-revalidate');

		echo $xml;

		$this->container->application->close();
	}

	public function deleteaccount(): void
	{
		$this->csrfProtection();

		$user = $this->container->userManager->getUser();

		if (!$user->getId())
		{
			$this->setRedirect($this->container->router->route('index.php?view=login'));

			return;
		}

		// Verify the typed username matches
		$typedUsername = $this->input->post->getString('confirm_username', '');

		if ($typedUsername !== $user->getUsername())
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=userconsent'),
				$this->getLanguage()->text('PANOPTICON_USERCONSENT_ERR_USERNAME_MISMATCH'),
				'error'
			);

			return;
		}

		/** @var Users $model */
		$model  = $this->container->mvcFactory->makeTempModel('Users');
		$userId = $user->getId();

		// Check if user can self-delete
		if (!$model->canSelfDelete($userId))
		{
			$this->setRedirect(
				$this->container->router->route('index.php?view=userconsent'),
				$this->getLanguage()->text('PANOPTICON_USERCONSENT_ERR_CANNOT_SELF_DELETE'),
				'error'
			);

			return;
		}

		// Set the self_delete flag and delete the user
		$model->setState('self_delete', true);
		$model->find($userId);
		$model->delete($userId);

		// Destroy session
		$this->container->userManager->logoutUser();
		$this->container->session->destroy();

		$this->setRedirect(
			$this->container->router->route('index.php?view=login'),
			$this->getLanguage()->text('PANOPTICON_USERCONSENT_MSG_ACCOUNT_DELETED')
		);
	}
}
