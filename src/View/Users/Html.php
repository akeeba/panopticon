<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Users;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Users;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Akeeba\Panopticon\View\Trait\ShowOnTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Utils\Template;

class Html extends BaseHtmlView
{
	/**
	 * The MFA methods available for this user
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	public array $methods = [];

	/**
	 * Are there any active TFA methods at all?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public bool $mfaActive = false;

	/**
	 * Which method has the default record?
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public string $defaultMethod = '';

	protected bool $canEditMFA = false;

	use ShowOnTrait;
	use CrudTasksTrait
	{
		onBeforeAdd as onBeforeAddCrud;
		onBeforeEdit as onBeforeEditCrud;
	}

	protected function onBeforeAdd()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);

		return $this->onBeforeAddCrud();
	}

	protected function onBeforeEdit()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, {
            html: true
        }))
    });

JS;
		$this->container->application->getDocument()->addScriptDeclaration($js);

		$ret = $this->onBeforeEditCrud();

		if ($ret)
		{
			$this->prepareMFAProperties();
		}

		return $ret;
	}

	protected function onBeforeRead()
	{
		$this->setStrictLayout(true);
		$this->setStrictTpl(true);

		$this->addButton('back', ['url' => 'javascript:history.back()']);

		return parent::onBeforeRead();
	}

	/**
	 * Prepares the properties required for Multi-factor Authentication setup
	 *
	 * @return void
	 */
	private function prepareMFAProperties()
	{
		/** @var Users $item */
		$item        = $this->getModel();
		$currentUser = $this->container->userManager->getUser();
		$editedUser  = $this->container->userManager->getUser($item->getId());

		$this->canEditMFA =
			$item->getId() > 0
			&& (
				$currentUser->getId() == $item->getId()
				|| (
					$currentUser->getPrivilege('panopticon.super')
					&& !$editedUser->getPrivilege('panopticon.super')
				)
			);

		if (!$this->canEditMFA)
		{
			return;
		}

		$model = $this->container->mvcFactory->makeTempModel('Mfamethods');

		if ($this->getLayout() != 'firsttime')
		{
			$this->setLayout('form');
		}

		$this->methods = $model->getMethods($editedUser);
		$activeRecords = 0;

		foreach ($this->methods as $methodName => $method)
		{
			$methodActiveRecords = count($method['active']);

			if (!$methodActiveRecords)
			{
				continue;
			}

			$activeRecords   += $methodActiveRecords;
			$this->mfaActive = true;

			foreach ($method['active'] as $record)
			{
				if ($record->default)
				{
					$this->defaultMethod = $methodName;

					break;
				}
			}
		}

		$model       = $this->container->mvcFactory->makeTempModel('Backupcodes');
		$backupCodes = $model->getBackupCodes($editedUser);

		if ($activeRecords && empty($backupCodes))
		{
			$model->regenerateBackupCodes($editedUser);
		}

		$backupCodesRecord = $model->getBackupCodesRecord($editedUser);

		if (!is_null($backupCodesRecord))
		{
			$this->methods['backupcodes'] = [
				'name'          => 'backupcodes',
				'display'       => $this->getLanguage()->text('PANOPTICON_MFA_LBL_BACKUPCODES'),
				'shortinfo'     => $this->getLanguage()->text('PANOPTICON_MFA_LBL_BACKUPCODES_DESCRIPTION'),
				'image'         => 'media/mfa/images/emergency.svg',
				'canDisable'    => false,
				'allowMultiple' => false,
				'active'        => [$backupCodesRecord],
			];
		}
	}

}