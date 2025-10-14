<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\MultiFactorAuth\Helper as MfaHelper;
use Awf\Mvc\DataModel;
use RuntimeException;

/**
 * Model for the Multi-Factor Authentication records
 *
 * @property int    $id          Record ID.
 * @property int    $user_id     User ID
 * @property string $title       Record title.
 * @property string $method      MFA Method (corresponds to one of the plugins).
 * @property int    $default     Is this the default Method?
 * @property string $options     JSON-encoded options for the MFA Method.
 * @property string $created_on  Date and time the record was created.
 * @property string $last_used   Date and time the record was last used successfully.
 *
 * @since 1.0.0
 */
class Mfa extends DataModel
{
	/**
	 * Delete flags per ID, set up onBeforeDelete and used onAfterDelete
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private array $deleteFlags = [];

	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__mfa';
		$this->idFieldName = 'id';

		parent::__construct($container);
	}

	public function save($data = null, $orderingFilter = '', $ignore = null)
	{
		if (empty($this->last_used))
		{
			$this->last_used = null;
		}

		$records = MfaHelper::getUserMfaRecords($this->getContainer(), $this->user_id);

		// Existing record: remove it from the list of records.
		if ($this->getId() > 0)
		{
			$records = array_filter(
				$records,
				function ($rec) {
					return $rec->id != $this->id;
				}
			);
		}

		// Update the dates on a new record
		if (empty($this->id))
		{
			$this->created_on = ($this->container->dateFactory())->toSql();
			$this->last_used  = null;
		}

		// Do I need to mark this record as the default?
		if ($this->default == 0)
		{
			$hasDefaultRecord = array_reduce(
				$records,
				function (bool $carry, Mfa $record): bool {
					return $carry || ($record->default == 1);
				},
				false
			);

			$this->default = $hasDefaultRecord ? 0 : 1;
		}

		// Let's find out if we are saving a new MFA method record without having backup codes yet.
		$mustCreateBackupCodes = false;

		if (((int) $this->getId() === 0) && $this->method !== 'backupcodes')
		{
			// Do I have any backup records?
			$hasBackupCodes = array_reduce(
				$records,
				function (bool $carry, $record) {
					return $carry || $record->method === 'backupcodes';
				},
				false
			);

			$mustCreateBackupCodes = !$hasBackupCodes;

			// If the only other entry is the backup records one I need to make this the default method
			if ($hasBackupCodes && count($records) === 1)
			{
				$this->default = 1;
			}
		}

		parent::save($data, $orderingFilter, $ignore);

		// If this record is the default unset the default flag from all other records
		$this->switchDefaultRecord();

		// Do I need to generate backup codes?
		if ($mustCreateBackupCodes)
		{
			$this->generateBackupCodes();
		}

		return $this;
	}

	public function getOptions(): array
	{
		try
		{
			return is_array($this->options) ? $this->options : (@json_decode($this->options, true) ?: []);
		}
		catch (\Exception $e)
		{
			return [];
		}
	}


	protected function onBeforeDelete(int &$pk): bool
	{
		$record = $this;

		if ($pk != $this->getId())
		{
			$record = clone $this;
			$record->reset();
			$record->findOrFail($pk);
		}

		$user = $this->container->userManager->getUser();

		// You can only delete your own records, unless you're a superuser
		if (($record->user_id != $user->getId()) && !MfaHelper::canEditUser($user))
		{
			throw new RuntimeException($this->getLanguage()->text('canEditUser'), 403);
		}

		// Save flags used onAfterDelete
		$this->deleteFlags[$record->id] = [
			'default'    => $record->default,
			'numRecords' => $this->getNumRecords($record->user_id),
			'user_id'    => $record->user_id,
			'method'     => $record->method,
		];

		return true;
	}

	protected function onAfterDelete(int &$pk): bool
	{
		if (is_array($pk))
		{
			$pk = array_shift($pk);
		}

		if (!isset($this->deleteFlags[$pk]))
		{
			return true;
		}

		if (($this->deleteFlags[$pk]['numRecords'] <= 2) && ($this->deleteFlags[$pk]['method'] != 'backupcodes'))
		{
			/**
			 * This was the second to last MFA record in the database (the last one is the `backupcodes`). Therefore, we
			 * need to delete the remaining entry and go away. We don't trigger this if the Method we are deleting was
			 * the `backupcodes` because we might just be regenerating the backup codes.
			 */
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__mfa'))
				->where($db->quoteName('user_id') . ' = ' . $db->quote($this->deleteFlags[$pk]['user_id']));

			$db->setQuery($query)->execute();

			unset($this->deleteFlags[$pk]);

			return true;
		}

		// This was the default record. Promote the next available record to default.
		if ($this->deleteFlags[$pk]['default'])
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__mfa'))
				->where($db->quoteName('user_id') . ' = ' . $db->quote($this->deleteFlags[$pk]['user_id']));

			$ids   = $db->setQuery($query)->loadColumn();

			if (empty($ids))
			{
				return true;
			}

			$id    = array_shift($ids);
			$query = $db->getQuery(true)
				->update($db->quoteName('#__mfa'))
				->set($db->qn('default') . ' = 1')
				->where($db->quoteName('id') . ' = ' . $db->quote($id));

			$db->setQuery($query)->execute();
		}

		return true;
	}

	/**
	 * If this record is set to be the default, unset the default flag from the other records for the same user.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function switchDefaultRecord(): void
	{
		if (!$this->default)
		{
			return;
		}

		// This record is marked as default, so we must unset the default flag from all other records for this user.
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__mfa'))
			->set($db->quoteName('default') . ' = 0')
			->where($db->quoteName('user_id') . ' = ' . $db->quote($this->user_id))
			->where($db->quoteName('id') . ' != ' . $db->quote($this->getId()));

		$db->setQuery($query)->execute();
	}

	/**
	 * Get the number of MFA records for a user ID
	 *
	 * @param   int  $userId  The user ID to check
	 *
	 * @return  integer
	 *
	 * @since   4.2.0
	 */
	private function getNumRecords(int $userId): int
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__mfa'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		return (int) ($db->setQuery($query)->loadResult() ?: 0);
	}

}