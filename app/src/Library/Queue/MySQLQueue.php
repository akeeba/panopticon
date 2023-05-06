<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Queue;


use Awf\Database\Driver;
use DateTime;
use Exception;

defined('AKEEBA') || die;

class MySQLQueue implements QueueInterface
{
	public function __construct(private string $queueIdentifier, private Driver $db, private string $tableName = '#__queue')
	{
	}

	public function push(QueueItem $item, DateTime|int|string|null $time): void
	{
		$db        = $this->db;
		$timestamp = $this->normaliseTime($time);
		$query     = $db->getQuery(true)
			->insert($db->quoteName($this->tableName))
			->columns([
				$db->quoteName('item'),
				$db->quoteName('time'),
			])
			->values(
				$db->quote(json_encode($item)) . ',' .
				$time === null ? 'NULL' : $timestamp
			);

		$db->transactionStart();
		$db->setQuery($query)->execute();
		$db->transactionCommit();
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
				'JSON_EXTRACT(' . $db->quoteName('item') . ', \'$.queueType\') = ' . $db->quote($this->queueIdentifier),
			])
			->order($db->quoteName('time') . 'ASC');
		// Append this because we can't do this with the query interface
		$query = (string) $query . ' LIMIT 0, 1 FOR UPDATE';

		// Wrap the select and delete in a transaction
		$db->transactionStart();

		$object = $db->setQuery($query)->loadObject();

		if (empty($object))
		{
			// Nothing to return. Roll back the transaction.
			$db->transactionRollback();

			return null;
		}

		$query = $db->getQuery(true)
			->delete($db->quoteName($this->tableName))
			->where($db->quoteName('id') . ' = ' . $object->id);
		$db->setQuery($query)->execute();

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
		$db->setQuery($query)->execute();
		$db->transactionCommit();
	}

	public function count(): int
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName($this->tableName))
			->where('JSON_EXTRACT(' . $db->quoteName('item') . ', \'$.queueType\') = ' . $db->quote($this->queueIdentifier));

		return $db->setQuery($query)->loadColumn() ?: 0;
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
			$query->where(
				sprintf(
					"JSON_EXTRACT(%s, %s) = %s",
					$db->quoteName('item'),
					$db->quote('$.' . $key),
					$db->quote($value)
				)
			);
		}

		return $db->setQuery($query)->loadColumn() ?: 0;
	}

	private function normaliseTime(DateTime|int|string|null $time): int
	{
		if (empty($time))
		{
			$time = new DateTime();
		}

		if (is_integer($time))
		{
			return $time;
		}

		if (is_string($time))
		{
			try
			{
				$time = new DateTime($time);
			}
			catch (Exception $e)
			{
				$time = new DateTime();
			}
		}

		try
		{
			return $time->getTimestamp();
		}
		catch (Exception $e)
		{
			return $time->format('U');
		}
	}
}