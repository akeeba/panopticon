<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Users;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Model\Passkeys;
use Akeeba\Panopticon\Model\Users;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Akeeba\Panopticon\View\Trait\ShowOnTrait;
use Awf\Inflector\Inflector;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Utils\Template;
use JetBrains\PhpStorm\ArrayShape;

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

	/** @var array{enabled: bool, user: ?\Akeeba\Panopticon\Library\User\User, allow_add: bool, credentials: array, error: ?string, showImages: bool} */
	#[ArrayShape([
		'enabled'     => 'bool',
		'user'        => '\Akeeba\Panopticon\Library\User\User|null',
		'allow_add'   => 'bool',
		'credentials' => 'array',
		'error'       => 'string|null',
		'showImages'  => 'bool',
	])]
	protected array $passkeyVariables = [];

	public bool $collapseForMFA = false;

	use ShowOnTrait;
	use CrudTasksTrait
	{
		onBeforeAdd as onBeforeAddCrud;
		onBeforeEdit as onBeforeEditCrud;
	}

	public function onBeforeBrowse(): bool
	{
		$this->addButtons(['add', 'edit', 'delete']);

		if (empty($this->getTitle()))
		{
			$this->setTitle(
				$this->getLanguage()->text('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE')
			);
		}

		// If no list limit is set, use the Panopticon default (50) instead of All (AWF's default).
		$limit = $this->getModel()->getState('limit', 50, 'int');
		$this->getModel()->setState('limit', $limit);

		return parent::onBeforeBrowse();
	}


	protected function onBeforeAdd()
	{
		Template::addJs('media://js/showon.js', $this->getContainer()->application, defer: true);

		return $this->onBeforeAddCrud();
	}

	protected function onBeforeEdit()
	{
		$app = $this->getContainer()->application;

		Template::addJs('media://js/showon.js', $app, defer: true);

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

		if (!$ret)
		{
			return $ret;
		}

		$this->prepareMFAProperties();
		$this->passkeyVariables = $this->container
			->mvcFactory
			->makeTempModel('Passkeys')
			->getDisplayVariables($this->container->userManager->getUser($this->getModel()->getId()));

		if ($this->passkeyVariables['enabled'] && $this->passkeyVariables['allow_add'])
		{
			$router = $this->getContainer()->router;
			$doc    = $app->getDocument();
			$token  = $this->getContainer()->session->getCsrfToken()->getValue();

			$doc->lang('PANOPTICON_PASSKEYS_ERR_LABEL_NOT_SAVED');
			$doc->lang('PANOPTICON_PASSKEYS_ERR_NOT_DELETED');
			$doc->lang('PANOPTICON_PASSKEYS_ERR_NO_BROWSER_SUPPORT');
			$doc->lang('PANOPTICON_PASSKEYS_ERR_XHR_INITCREATE');
			$doc->lang('PANOPTICON_PASSKEYS_MANAGE_BTN_CANCEL_LABEL');
			$doc->lang('PANOPTICON_PASSKEYS_MANAGE_BTN_SAVE_LABEL');
			$doc->addScriptOptions(
				'panopticon.passkey',
				[
					'initURL'      => $router->route(
						sprintf("index.php?view=Passkeys&task=initCreate&format=json&%s=1", $token)
					),
					'createURL'    => $router->route(
						sprintf("index.php?view=Passkeys&task=create&format=raw&%s=1", $token)
					),
					'saveLabelURL' => $router->route(
						sprintf("index.php?view=Passkeys&task=saveLabel&format=json&%s=1", $token)
					),
					'deleteURL'    => $router->route(
						sprintf("index.php?view=Passkeys&task=delete&format=json&%s=1", $token)
					),
				]
			);
		}

		if ($this->collapseForMFA)
		{
			$this->container->application->getDocument()->getToolbar()->removeButtonByName('save');
			$this->container->application->getDocument()->getToolbar()->removeButtonByName('apply');
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