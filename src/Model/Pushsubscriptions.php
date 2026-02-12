<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Awf\Mvc\DataModel;

/**
 * Model for Web Push notification subscriptions
 *
 * @property int    $id         Record ID.
 * @property int    $user_id    User ID.
 * @property string $endpoint   Push service endpoint URL.
 * @property string $key_p256dh Public key for encryption.
 * @property string $key_auth   Authentication secret.
 * @property string $encoding   Content encoding (default: aesgcm).
 * @property string $user_agent User agent string of the subscribing browser.
 * @property string $created_on Date and time the subscription was created.
 *
 * @since  1.3.0
 */
class Pushsubscriptions extends DataModel
{
	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__push_subscriptions';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	/**
	 * Get all push subscriptions for a given user.
	 *
	 * @param   int  $userId  The user ID.
	 *
	 * @return  array  Array of subscription objects.
	 * @since   1.3.0
	 */
	public function getSubscriptionsForUser(int $userId): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__push_subscriptions'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Get all push subscriptions for multiple users.
	 *
	 * @param   array  $userIds  Array of user IDs.
	 *
	 * @return  array  Array of subscription objects.
	 * @since   1.3.0
	 */
	public function getSubscriptionsForUsers(array $userIds): array
	{
		if (empty($userIds))
		{
			return [];
		}

		$db      = $this->getDbo();
		$userIds = array_map('intval', $userIds);
		$query   = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__push_subscriptions'))
			->where($db->quoteName('user_id') . ' IN(' . implode(',', $userIds) . ')');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Remove a subscription by its endpoint URL.
	 *
	 * @param   string  $endpoint  The push service endpoint URL.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function removeByEndpoint(string $endpoint): void
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__push_subscriptions'))
			->where($db->quoteName('endpoint') . ' = ' . $db->quote($endpoint));

		$db->setQuery($query)->execute();
	}

	/**
	 * Remove all subscriptions for a user.
	 *
	 * @param   int  $userId  The user ID.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function removeAllForUser(int $userId): void
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__push_subscriptions'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		$db->setQuery($query)->execute();
	}
}
