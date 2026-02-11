<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Userconsent;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Policies;
use Akeeba\Panopticon\Model\Users;
use Awf\Mvc\View;

class Html extends View
{
	public string $tosContent = '';

	public string $privacyContent = '';

	public bool $canSelfDelete = false;

	public string $username = '';

	public function onBeforeDefault(): bool
	{
		$this->setTitle($this->getLanguage()->text('PANOPTICON_USERCONSENT_TITLE'));

		// Disable main menu for captive flow
		$this->container->application->getDocument()->getMenu()->disableMenu();

		/** @var Policies $policiesModel */
		$policiesModel = $this->container->mvcFactory->makeTempModel('Policies');

		$this->tosContent     = $policiesModel->getContent('tos');
		$this->privacyContent = $policiesModel->getContent('privacy');

		$user = $this->container->userManager->getUser();

		/** @var Users $usersModel */
		$usersModel = $this->container->mvcFactory->makeTempModel('Users');

		$this->canSelfDelete = $usersModel->canSelfDelete($user->getId());
		$this->username      = $user->getUsername();

		return true;
	}
}
