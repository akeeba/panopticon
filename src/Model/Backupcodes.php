<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\User\User;

class Backupcodes extends Model
{
	protected array $cache = [];

	/**
	 * Get the backup codes record for the specified user
	 *
	 * @param   User|null  $user  The user in question. Use null for the currently logged in user.
	 *
	 * @return  Mfa|null  Record object or null if none is found
	 * @throws  \Exception
	 */
	public function getBackupCodesRecord(User $user = null): ?Mfa
	{
		// Make sure I have a user
		$user ??= $this->container->userManager->getUser();

		/** @var Mfa $record */
		$record = Model::getTmpInstance('', 'Mfa', $this->container);

		try
		{
			return $record->findOrFail([
				'user_id' => $user->getId(),
				'method'  => 'backupcodes',
			]);
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	/**
	 * Generate a new set of backup codes for the specified user. The generated codes are immediately saved to the
	 * database and the internal cache is updated.
	 *
	 * @param   User|null  $user  Which user to generate codes for?
	 *
	 * @throws \Exception
	 */
	public function regenerateBackupCodes(User $user = null): void
	{
		// Make sure I have a user
		$user ??= $this->container->userManager->getUser();

		// Generate backup codes
		$backupCodes = [];

		for ($i = 0; $i < 10; $i++)
		{
			// Each backup code is 2 groups of 4 digits
			$backupCodes[$i] = sprintf('%04u%04u', random_int(0, 9999), random_int(0, 9999));
		}

		// Save the backup codes to the database and update the cache
		$this->saveBackupCodes($backupCodes, $user);
	}

	/**
	 * Saves the backup codes to the database
	 *
	 * @param   array      $codes  An array of exactly 10 elements
	 * @param   User|null  $user   The user for which to save the backup codes
	 *
	 * @return  bool
	 * @throws  \Exception
	 */
	public function saveBackupCodes(array $codes, ?User $user = null): bool
	{
		// Make sure I have a user
		$user ??= $this->container->userManager->getUser();

		// Try to load existing backup codes
		$existingCodes = $this->getBackupCodes($user);
		$now           = new Date();

		/** @var Mfa $record */
		$record = Model::getTmpInstance('', 'Mfa', $this->container);

		if (is_null($existingCodes))
		{
			$record->reset();

			$newData = [
				'user_id'    => $user->getId(),
				'title'      => 'Backup Codes',
				'method'     => 'backupcodes',
				'default'    => 0,
				'created_on' => $now->toSql(),
				'options'    => json_encode($codes),
			];
		}
		else
		{
			$record->findOrFail([
				'user_id' => $user->getId(),
				'method'  => 'backupcodes',
			]);

			$newData = [
				'options' => json_encode($codes),
			];
		}

		$saved = $record->save($newData);

		if (!$saved)
		{
			return false;
		}

		// Finally, update the cache
		$this->cache[$user->getId()] = $codes;

		return true;
	}

	/**
	 * Returns the backup codes for the specified user. Cached values will be preferentially returned, therefore you
	 * MUST go through this model's Methods ONLY when dealing with backup codes.
	 *
	 * @param   User|null  $user  The user for which you want the backup codes
	 *
	 * @return  array|null  The backup codes, or null if they do not exist
	 * @throws  \Exception
	 */
	public function getBackupCodes(User $user = null): ?array
	{
		// Make sure I have a user
		$user ??= $this->container->userManager->getUser();

		if (isset($this->cache[$user->getId()]))
		{
			return $this->cache[$user->getId()];
		}

		// If there is no cached record try to load it from the database
		$this->cache[$user->getId()] = null;

		// Try to load the record
		/** @var Mfa $record */
		$record = Model::getTmpInstance('', 'Mfa', $this->container);

		try
		{
			$record->findOrFail([
				'user_id' => $user->getId(),
				'method'  => 'backupcodes',
			]);

			$this->cache[$user->getId()] = json_decode($record->options);
		}
		catch (\Exception $e)
		{
			// We leave it as NULL
		}

		return $this->cache[$user->getId()];
	}

	/**
	 * Check if the provided string is a backup code. If it is, it will be removed from the list (replaced with an empty
	 * string) and the codes will be saved to the database. All comparisons are performed in a timing safe manner.
	 *
	 * @param   string     $code  The code to check
	 * @param   User|null  $user  The user to check against
	 *
	 * @return  bool
	 * @throws  \Exception
	 */
	public function isBackupCode($code, ?User $user = null): bool
	{
		// Load the backup codes
		$codes = $this->getBackupCodes($user) ?: array_fill(0, 10, '');

		// Keep only the numbers in the provided $code
		$code = filter_var($code, FILTER_SANITIZE_NUMBER_INT);
		$code = trim($code);

		// Check if the code is in the array. We always check against ten codes to prevent timing attacks which
		// determine the amount of codes.
		$result = false;

		// The two arrays let us always add an element to an array, therefore having PHP expend the same amount of time
		// for the correct code, the incorrect codes and the fake codes.
		$newArray   = [];
		$dummyArray = [];

		$realLength = count($codes);
		$restLength = 10 - $realLength;

		for ($i = 0; $i < $realLength; $i++)
		{
			if (hash_equals($codes[$i], $code))
			{
				// This may seem redundant but makes sure both branches of the if-block are isochronous
				$result       = $result || true;
				$newArray[]   = '';
				$dummyArray[] = $codes[$i];
			}
			else
			{
				// This may seem redundant but makes sure both branches of the if-block are isochronous
				$result       = $result || false;
				$dummyArray[] = '';
				$newArray[]   = $codes[$i];
			}
		}

		// This is am intentional waste of time, symmetrical to the code above, making sure evaluating each of the total
		// of ten elements takes the same time. This code should never run UNLESS someone messed up with our backup
		// codes array and it no longer contains 10 elements.
		$otherResult = false;

		$temp1 = '';

		for ($i = 0; $i < 10; $i++)
		{
			$temp1[$i] = random_int(0, 99999999);
		}

		for ($i = 0; $i < $restLength; $i++)
		{
			if (hash_equals($temp1[$i], $code))
			{
				$otherResult  = $otherResult || true;
				$newArray[]   = '';
				$dummyArray[] = $temp1[$i];
			}
			else
			{
				$otherResult  = $otherResult || false;
				$newArray[]   = '';
				$dummyArray[] = $temp1[$i];
			}
		}

		// This last check makes sure that an empty code does not validate
		$result = $result && !hash_equals('', $code);

		// Save the backup codes
		$this->saveBackupCodes($newArray, $user);

		// Finally return the result
		return $result;
	}

}