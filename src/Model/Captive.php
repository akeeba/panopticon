<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\MultiFactorAuth\DataShape\CaptiveRenderOptions;
use Akeeba\Panopticon\Library\MultiFactorAuth\Helper as MfaHelper;
use Akeeba\Panopticon\Library\User\User;
use Awf\Mvc\Model;
use Exception;

/**
 * MFA Captive Page Model
 *
 * @since  1.0.0
 */
class Captive extends Model
{
	/**
	 * Cache of the names of the currently active MFA Methods
	 *
	 * @var  null
	 */
	protected $activeMFAMethodNames = null;

	/**
	 * Get the currently selected MFA record for the current user.
	 *
	 * If the record ID is empty, it does not correspond to the currently logged-in user or does not correspond to an
	 * active plugin null is returned instead.
	 *
	 * @param   User|null  $user  The user for which to fetch records. Skip to use the current user.
	 *
	 * @return  Mfa|null
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function getRecord(?User $user = null): ?Mfa
	{
		$id = (int) $this->getState('record_id', null);

		if ($id <= 0)
		{
			return null;
		}

		$user ??= $this->container->userManager->getUser();

		/** @var Mfa $record */
		$record = $this->getContainer()->mvcFactory->makeTempModel('Mfa');

		try
		{
			$record->findOrFail(
				[
					'user_id' => $user->getId(),
					'id'      => $id,
				]
			);
		}
		catch (Exception)
		{
			return null;
		}

		$methodNames = $this->getActiveMethodNames();

		if (!in_array($record->method, $methodNames) && ($record->method != 'backupcodes'))
		{
			return null;
		}

		return $record;
	}

	/**
	 * Get the MFA records for the user which correspond to active plugins
	 *
	 * @param   User|null  $user                The user for which to fetch records. Skip to use the current user.
	 * @param   bool       $includeBackupCodes  Should I include the backup codes record?
	 *
	 * @return  Mfa[]
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function getRecords(User $user = null, bool $includeBackupCodes = false): array
	{
		$user ??= $this->container->userManager->getUser();

		// Get the user's MFA records
		$records = MfaHelper::getUserMfaRecords($this->getContainer(), $user->getId());

		// No MFA Methods? Then we obviously don't need to display a Captive login page.
		if (empty($records))
		{
			return [];
		}

		// Get the enabled MFA Methods' names
		$methodNames = $this->getActiveMethodNames();

		// Filter the records based on currently active MFA Methods
		$ret = [];

		$methodNames[] = 'backupcodes';
		$methodNames   = array_unique($methodNames);

		if (!$includeBackupCodes)
		{
			$methodNames = array_filter($methodNames, fn($method) => $method != 'backupcodes');
		}

		/** @var Mfa $record */
		foreach ($records as $record)
		{
			// Backup codes must not be included in the list. We add them in the View, at the end of the list.
			if (in_array($record->method, $methodNames))
			{
				$ret[$record->id] = $record;
			}
		}

		return $ret;
	}

	/**
	 * Load the Captive login page render options for a specific MFA record
	 *
	 * @param   Mfa|null  $record  The MFA record to process
	 *
	 * @return  CaptiveRenderOptions  The rendering options
	 * @since   1.0.0
	 */
	public function loadCaptiveRenderOptions(?Mfa $record): CaptiveRenderOptions
	{
		$renderOptions = new CaptiveRenderOptions();

		if (empty($record))
		{
			return $renderOptions;
		}

		$results = $this->container->eventDispatcher->trigger('onMfaCaptive', [$record]);

		if (empty($results))
		{
			return $renderOptions;
		}

		foreach ($results as $result)
		{
			if (empty($result))
			{
				continue;
			}

			return $renderOptions->merge($result);
		}

		if ($record->method === 'backupcodes')
		{
			$renderOptions->label = $this->getLanguage()->text('PANOPTICON_MFA_LBL_BACKUPCODES_ENTRY_LABEL');
		}

		return $renderOptions;
	}

	/**
	 * Translate an MFA Method's name into its human-readable, display name
	 *
	 * @param   string  $name  The internal MFA Method name
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function translateMethodName(string $name): string
	{
		static $map = null;

		if (!is_array($map))
		{
			$map        = [];
			$mfaMethods = MfaHelper::getMfaMethods();

			if (!empty($mfaMethods))
			{
				foreach ($mfaMethods as $mfaMethod)
				{
					$map[$mfaMethod['name']] = $mfaMethod['display'];
				}
			}
		}

		if ($name == 'backupcodes')
		{
			return $this->getLanguage()->text('PANOPTICON_MFA_LBL_BACKUPCODES_METHOD_NAME');
		}

		return $map[$name] ?? $name;
	}

	/**
	 * Return all the active MFA Methods' names
	 *
	 * @return  array|null
	 * @since   1.0.0
	 */
	private function getActiveMethodNames(): ?array
	{
		if (!is_null($this->activeMFAMethodNames))
		{
			return $this->activeMFAMethodNames;
		}

		// Let's get a list of all currently active MFA Methods
		$mfaMethods = MfaHelper::getMfaMethods();

		// If not MFA Method is active we can't really display a Captive login page.
		if (empty($mfaMethods))
		{
			$this->activeMFAMethodNames = [];

			return $this->activeMFAMethodNames;
		}

		// Get a list of just the Method names
		$this->activeMFAMethodNames = [];

		foreach ($mfaMethods as $mfaMethod)
		{
			$this->activeMFAMethodNames[] = $mfaMethod['name'];
		}

		return $this->activeMFAMethodNames;
	}
}