<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Security\ForbiddenIpRanges;
use Awf\Mvc\DataModel;
use RuntimeException;

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
	 * Validate the push endpoint before the subscription is saved.
	 *
	 * The endpoint comes from the user's browser and the server later POSTs to it. Push services are only reachable
	 * over HTTPS, so anything else is refused. The Forbidden IP Ranges policy applies too, as it does for site URLs;
	 * SendWebPush enforces it again at send time, through the HTTP factory's client.
	 *
	 * @return  static
	 * @throws  RuntimeException  When the endpoint is not an https: URL, or its host is in a forbidden IP range
	 * @since   2.5.0
	 */
	public function check()
	{
		parent::check();

		$endpoint = (string) $this->endpoint;
		$parts    = parse_url($endpoint);

		if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']))
		{
			throw new RuntimeException('Invalid push endpoint');
		}

		$forbidden = ForbiddenIpRanges::fromConfig($this->getContainer());

		// The policy is disabled by default. Do no work — and no DNS lookup — when there are no ranges.
		if (!$forbidden->isEmpty() && $forbidden->isForbiddenHost($parts['host']))
		{
			// As for site URLs, the detail goes to the log and the user only gets a generic message.
			$this->getContainer()->loggerFactory->get('panopticon')->notice(
				sprintf(
					'Refused a push subscription for user #%d: the endpoint host (%s) is inside a forbidden IP range.',
					(int) $this->user_id,
					$parts['host']
				)
			);

			throw new RuntimeException('Invalid push endpoint');
		}

		return $this;
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
