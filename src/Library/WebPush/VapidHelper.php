<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\WebPush;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Minishlink\WebPush\VAPID;

/**
 * Helper for managing VAPID keys used in Web Push notifications.
 *
 * Stores and retrieves keys from `#__akeeba_common`. Auto-generates on first use.
 *
 * @since  1.3.0
 */
class VapidHelper
{
	private const KEY_PUBLIC = 'webpush.vapid.public';

	private const KEY_PRIVATE = 'webpush.vapid.private';

	private ?string $publicKey = null;

	private ?string $privateKey = null;

	public function __construct(private readonly Container $container)
	{
	}

	/**
	 * Get the VAPID public key, generating a new key pair if none exists.
	 *
	 * @return  string
	 * @since   1.3.0
	 */
	public function getPublicKey(): string
	{
		$this->ensureKeys();

		return $this->publicKey;
	}

	/**
	 * Get the VAPID private key, generating a new key pair if none exists.
	 *
	 * @return  string
	 * @since   1.3.0
	 */
	public function getPrivateKey(): string
	{
		$this->ensureKeys();

		return $this->privateKey;
	}

	/**
	 * Ensure keys are loaded from the database, generating them if they don't exist.
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	private function ensureKeys(): void
	{
		if ($this->publicKey !== null && $this->privateKey !== null)
		{
			return;
		}

		$db = $this->container->db;

		// Try to load existing keys
		$query = $db->getQuery(true)
			->select([$db->quoteName('key'), $db->quoteName('value')])
			->from($db->quoteName('#__akeeba_common'))
			->where(
				$db->quoteName('key') . ' IN(' .
				$db->quote(self::KEY_PUBLIC) . ',' .
				$db->quote(self::KEY_PRIVATE) . ')'
			);

		$rows = $db->setQuery($query)->loadObjectList('key') ?: [];

		if (isset($rows[self::KEY_PUBLIC], $rows[self::KEY_PRIVATE]))
		{
			$this->publicKey  = $rows[self::KEY_PUBLIC]->value;
			$this->privateKey = $rows[self::KEY_PRIVATE]->value;

			return;
		}

		// Generate new keys
		$keys = VAPID::createVapidKeys();

		$this->publicKey  = $keys['publicKey'];
		$this->privateKey = $keys['privateKey'];

		// Store them
		foreach ([self::KEY_PUBLIC => $this->publicKey, self::KEY_PRIVATE => $this->privateKey] as $key => $value)
		{
			$query = $db->getQuery(true)
				->insert($db->quoteName('#__akeeba_common'))
				->columns([$db->quoteName('key'), $db->quoteName('value')])
				->values($db->quote($key) . ',' . $db->quote($value));

			try
			{
				$db->setQuery($query)->execute();
			}
			catch (\Exception)
			{
				// Key may already exist (race condition); update instead
				$query = $db->getQuery(true)
					->update($db->quoteName('#__akeeba_common'))
					->set($db->quoteName('value') . ' = ' . $db->quote($value))
					->where($db->quoteName('key') . ' = ' . $db->quote($key));

				$db->setQuery($query)->execute();
			}
		}
	}
}
