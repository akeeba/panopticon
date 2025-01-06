<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\MultiFactorAuth;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\MultiFactorAuth\DataShape\MethodDescriptor;
use Akeeba\Panopticon\Model\Mfa;
use Awf\Container\Container;
use Awf\Mvc\Model;
use Awf\User\User;
use Exception;

abstract class Helper
{
	protected static ?array $allMFAs = null;

	public static function getMfaMethods(): array
	{
		return self::$allMFAs ?? call_user_func(function(): array {
			// Get all the plugin results
			$temp = Factory::getContainer()
				->eventDispatcher
				->trigger('onMfaGetMethod');

			// Normalize the results
			$ret = [];

			foreach ($temp as $method)
			{
				if (!is_array($method) && !($method instanceof MethodDescriptor))
				{
					continue;
				}

				$method = new MethodDescriptor($method);

				if (empty($method->name))
				{
					continue;
				}

				$ret[$method->name] = $method;
			}

			return $ret;
		});
	}

	/**
	 * Return all MFA records for a specific user
	 *
	 * @param   int|null  $user_id  User ID. NULL for currently logged in user.
	 *
	 * @return  Mfa[]
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public static function getUserMfaRecords(Container $container = null, ?int $user_id): array
	{
		if (empty($user_id))
		{
			$user    = Factory::getContainer()->userManager->getUser();
			$user_id = $user->getId() ?: 0;
		}

		$db    = Factory::getContainer()->db;
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__mfa'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user_id));

		try
		{
			$ids = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (Exception)
		{
			$ids = [];
		}

		if (empty($ids))
		{
			return [];
		}

		// Map all results to MFA table objects
		$records = array_map(function ($id) use ($container) {
			/** @var Mfa $record */
			$record = $container->mvcFactory->makeTempModel('Mfa');
			try
			{
				return $record->findOrFail($id);
			}
			catch (Exception)
			{
				return null;
			}
		}, $ids);

		// Let's remove Methods we couldn't decrypt when reading from the database.
		$hasBackupCodes = false;

		$records = array_filter($records, function ($record) use (&$hasBackupCodes) {
			$isValid = !is_null($record) && (!empty($record->options));

			if ($isValid && ($record->method === 'backupcodes'))
			{
				$hasBackupCodes = true;
			}

			return $isValid;
		});

		// If the only Method is backup codes it's as good as having no records
		if ((count($records) === 1) && $hasBackupCodes)
		{
			return [];
		}

		return $records;
	}

	public static function canEditUser(?User $user = null): bool
	{
		$myUser = Factory::getContainer()->userManager->getUser();

		// I can edit myself
		if (is_null($user) || $myUser->getId() === $user->getId())
		{
			return true;
		}

		// To edit another user I have to be super, and they have to be NOT.
		if (!$myUser->getPrivilege('panopticon.super') || $user->getPrivilege('panopticon.super'))
		{
			return false;
		}

		return true;
	}
}