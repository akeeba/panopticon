<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Mfamethods;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Model\Mfamethods;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	public array $renderOptions = [];

	public ?object $record = null;

	public ?string $returnURL = null;

	public ?User $user = null;

	public array $backupCodes = [];

	public bool $isEditExisting = false;

	public function display($tpl = null)
	{
		$this->user ??= $this->container->userManager->getUser();

		/** @var Mfamethods $model */
		$model = $this->getModel();
		$this->setLayout('form');
		$this->renderOptions = $model->getRenderOptions($this->user);
		$this->record        = $model->getRecord($this->user);

		// Backup codes are a special case, rendered with a special layout
		if ($this->record->method == 'backupcodes')
		{
			$this->setLayout('backupcodes');

			$backupCodes = is_array($this->record->options) ? $this->record->options : json_decode($this->record->options);
			$backupCodes = array_filter($backupCodes);

			if (count($backupCodes) % 2 != 0)
			{
				$backupCodes[] = '';
			}

			/**
			 * The call to array_merge resets the array indices. This is necessary since array_filter kept the indices,
			 * meaning our elements are completely out of order.
			 */
			$this->backupCodes = array_merge($backupCodes);
		}

		// Set up the isEditExisting property.
		$this->isEditExisting = !empty($this->record->id);

		// Set the page title
		$toolbar = $this->container->application->getDocument()->getToolbar();
		$toolbar->setTitle(
			$this->getLanguage()->text(($this->doTask !== 'add') ? 'PANOPTICON_MFA_LBL_TITLE_EDIT' : 'PANOPTICON_MFA_LBL_TITLE_ADD')
		);

		if ($this->record->method == 'backupcodes')
		{
			$cancelURL = $this->container->router->route('index.php?view=users&task=edit&id=' . $this->user->getId());

			if (!empty($this->returnURL))
			{
				$cancelURL = $this->escape(base64_decode($this->returnURL));
			}

			$refreshURL = $this->container->router->route(
				sprintf(
					"index.php?view=mfamethod&task=regenbackupcodes&user_id=%s&%s=1%s",
					$this->user->getId(),
					$this->container->session->getCsrfToken()->getValue(),
					empty($this->returnURL) ? '' : '&returnurl=' . $this->returnURL
				)
			);

			$toolbar->setTitle($this->getLanguage()->text('PANOPTICON_MFA_LBL_BACKUPCODES_METHOD_NAME'));

			$this->addButton('back', ['url' => $cancelURL]);
			$toolbar->addButtonFromDefinition([
				'id'    => 'reset',
				'title' => $this->getLanguage()->text('PANOPTICON_MFA_LBL_BACKUPCODES_RESET'),
				'class' => 'btn btn-danger',
				'url'   => $refreshURL,
				'icon'  => 'fa fa-refresh',
			]);
		}

		// TODO Set up the Help URL.
		$helpUrl = $this->renderOptions['help_url'];

		if (!empty($helpUrl))
		{
			$this->addButton('help', ['url' => $helpUrl]);
		}

		return parent::display($tpl);
	}


}