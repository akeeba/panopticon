<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Captive;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\MultiFactorAuth\DataShape\CaptiveRenderOptions;
use Akeeba\Panopticon\Library\MultiFactorAuth\Helper as MfaHelper;
use Akeeba\Panopticon\Model\Backupcodes;
use Akeeba\Panopticon\Model\Captive;
use Akeeba\Panopticon\Model\Mfa;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlview;

class Html extends BaseHtmlview
{
	use CrudTasksTrait;

	/**
	 * The MFA Method records for the current user which correspond to enabled plugins
	 *
	 * @var  array
	 */
	public array $records = [];

	/**
	 * The currently selected MFA Method record against which we'll be authenticating
	 *
	 * @var  Mfa|null
	 */
	public ?Mfa $record = null;

	/**
	 * The Captive MFA page's rendering options
	 *
	 * @var  CaptiveRenderOptions|null
	 */
	public ?CaptiveRenderOptions $renderOptions = null;

	/**
	 * Does the currently selected Method allow authenticating against all of its records?
	 *
	 * @var   bool
	 */
	public bool $allowEntryBatching = false;

	/**
	 * All enabled MFA Methods (plugins)
	 *
	 * @var   array
	 */
	public array $mfaMethods;

	public function display($tpl = null)
	{
		$user = $this->container->userManager->getUser();

		//onMfaBeforeDisplayMethods
		$this->container->eventDispatcher->trigger('onMfaBeforeDisplayMethods', [$user]);

		/** @var Captive $model */
		$model = $this->getModel();

		// Load data from the model
		$this->records    = $model->getRecords($user);
		$this->record     = $model->getRecord($user);
		$this->mfaMethods = MfaHelper::getMfaMethods();

		if (!empty($this->records))
		{
			/** @var Backupcodes $codesModel */
			$codesModel        = $this->getModel('Backupcodes');
			$backupCodesRecord = $codesModel->getBackupCodesRecord();

			if (!is_null($backupCodesRecord))
			{
				$backupCodesRecord->title = $this->getLanguage()->text('PANOPTICON_MFA_LBL_BACKUPCODES');
				$this->records[]          = $backupCodesRecord;
			}
		}

		// If we only have one record there's no point asking the user to select a MFA Method
		if (empty($this->record) && !empty($this->records))
		{
			// Default to the first record
			$this->record = reset($this->records);

			// If we have multiple records try to make this record the default
			if (count($this->records) > 1)
			{
				foreach ($this->records as $record)
				{
					if ($record->default)
					{
						$this->record = $record;

						break;
					}
				}
			}
		}

		// Set the correct layout based on the availability of an MFA record
		if ($this->getLayout() !== 'select')
		{
			$this->setLayout('default');
		}
		elseif (is_null($this->record))
		{
			$this->setLayout('select');
		}

		switch ($this->getLayout())
		{
			case 'select':
				$this->setTitle($this->getLanguage()->text('PANOPTICON_MFA_HEAD_SELECT_PAGE'));
				$this->allowEntryBatching = 1;
				break;

			case 'default':
			default:
				$this->renderOptions      = $model->loadCaptiveRenderOptions($this->record);
				$this->allowEntryBatching = $this->renderOptions['allowEntryBatching'] ?? 0;
				$this->setTitle($this->getLanguage()->text('PANOPTICON_MFA_HEAD_MFA_PAGE'));

			break;
		}


		return parent::display($tpl);
	}
}