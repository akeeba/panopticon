<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Queue;

defined('AKEEBA') || die;

use Awf\Database\Driver;
use Awf\Date\Date;
use DateTime;
use Exception;

class MySQLQueue implements QueueInterface
{
	public function __construct(private string $queueIdentifier, private Driver $db, private string $tableName = '#__queue')
	{
	}

	public function push(QueueItem $item, DateTime|int|string|null $time = null): void
	{
		$db    = $this->db;
		$time  = $this->normaliseTime($time);
		$query = $db->getQuery(true)
			->insert($db->quoteName($this->tableName))
			->columns([
				$db->quoteName('item'),
				$db->quoteName('time'),
			])
			->values(
				$db->quote(json_encode($item)) . ',' .
				$db->q($time->toSql())
			);

		$db->transactionStart();

		try
		{
			$db->setQuery($query)->execute();
			$db->transactionCommit();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();

			throw $e;
		}
	}

	public function pop(): ?QueueItem
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('item'),
			])
			->from($db->quoteName($this->tableName))
			->where([
				$db->quoteName('time') . ' <= NOW()',
				'JSON_EXTRACT(' . $db->quoteName('item') . ', \'$.queueType\') = ' . $db->quote(strtolower($this->queueIdentifier)),
			])
			->order($db->quoteName('time') . 'ASC');
		// Append this because we can't do this with the query interface
		$query = (string) $query . ' LIMIT 0, 1 FOR UPDATE';

		// Wrap the select and delete in a transaction
		$db->transactionStart();

		try
		{
			$object = $db->setQuery($db->replacePrefix((string) $query))->loadObject();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		if (empty($object))
		{
			// Nothing to return. Roll back the transaction.
			$db->transactionRollback();

			return null;
		}

		$query = $db->getQuery(true)
			->delete($db->quoteName($this->tableName))
			->where($db->quoteName('id') . ' = ' . $object->id);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		$db->transactionCommit();

		return QueueItem::fromJson($object->item);
	}

	public function clear(array $conditions = []): void
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->delete('IGNORE ' . $db->quoteName($this->tableName))
			->where('JSON_EXTRACT(' . $db->quoteName('item') . ', \'$.queueType\') = ' . $db->quote($this->queueIdentifier));

		if (isset($conditions['queueType']))
		{
			unset($conditions['queueType']);
		}

		foreach ($conditions as $key => $value)
		{
			$query->where(
				sprintf(
					"JSON_EXTRACT(%s, %s) = %s",
					$db->quoteName('item'),
					$db->quote('$.' . $key),
					$db->quote($value)
				)
			);
		}

		$db->transactionStart();

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		$db->transactionCommit();
	}

	public function count(): int
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName($this->tableName))
			->where('JSON_EXTRACT(' . $db->quoteName('item') . ', \'$.queueType\') = ' . $db->quote($this->queueIdentifier));

		return $db->setQuery($query)->loadResult() ?: 0;
	}

	public function countByCondition(array $conditions = []): int
	{
		if (isset($conditions['queueType']))
		{
			unset($conditions['queueType']);
		}

		if (empty($conditions))
		{
			return $this->count();
		}

		$db    = $this->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName($this->tableName))
			->where('JSON_EXTRACT(' . $db->quoteName('item') . ', \'$.queueType\') = ' . $db->quote($this->queueIdentifier));

		foreach ($conditions as $key => $value)
		{
			if (is_integer($value))
			{
				$value = intval($value);
			}
			elseif (is_numeric($value))
			{
				$value = floatval($value);
			}
			else
			{
				$value = $db->quote($value);
			}

			$query->where(
				sprintf(
					"JSON_EXTRACT(%s, %s) = %s",
					$db->quoteName('item'),
					$db->quote('$.' . $key),
					$value
				)
			);
		}

		return $db->setQuery($query)->loadResult() ?: 0;
	}

	private function normaliseTime(DateTime|int|string|null $time): Date
	{
		if (empty($time))
		{
			$time = new Date();
		}

		if (is_integer($time))
		{
			$time = Date('@' . $time);
		}

		if ($time instanceof DateTime)
		{
			$time = $time->format(DATE_RFC3339);
		}

		if (is_string($time))
		{
			try
			{
				$time = new Date($time);
			}
			catch (Exception $e)
			{
				$time = new Date();
			}
		}

		return $time;
	}
}