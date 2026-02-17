<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Awf\Date\Date;
use Awf\Mvc\DataModel;

/**
 * Model for API tokens
 *
 * @property int         $id          Record ID.
 * @property int         $user_id     User ID the token is for.
 * @property string|null $description Token description.
 * @property string      $seed        Base64-encoded random seed.
 * @property int         $enabled     Is the token enabled?
 * @property int         $created_by  User who created the token.
 * @property Date        $created_on  When the token was created.
 * @property int|null    $modified_by User who last modified the token.
 * @property Date|null   $modified_on When the token was last modified.
 *
 * @since  1.4.0
 */
class Apitoken extends DataModel
{
	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__api_tokens';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	/**
	 * Generate a random seed for a new API token.
	 *
	 * @return  string  Base64-encoded random 64 bytes.
	 * @since   1.4.0
	 */
	public static function generateSeed(): string
	{
		return base64_encode(random_bytes(64));
	}

	/**
	 * Compute the API token string from a seed and user ID.
	 *
	 * @param   string  $seed        The base64-encoded seed.
	 * @param   int     $userId      The user ID.
	 * @param   string  $siteSecret  The site secret key.
	 *
	 * @return  string  The API token string (base64-encoded).
	 * @since   1.4.0
	 */
	public static function computeToken(string $seed, int $userId, string $siteSecret): string
	{
		return base64_encode(
			'SHA-256:' . $userId . ':' . hash_hmac('sha256', base64_decode($seed), $siteSecret)
		);
	}

	/**
	 * Get all enabled tokens for a given user ID.
	 *
	 * @param   int  $userId  The user ID.
	 *
	 * @return  array  Array of Apitoken objects.
	 * @since   1.4.0
	 */
	public function getEnabledTokensForUser(int $userId): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__api_tokens'))
			->where($db->quoteName('enabled') . ' = 1')
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Get all tokens for a given user ID.
	 *
	 * @param   int  $userId  The user ID.
	 *
	 * @return  array  Array of token row objects.
	 * @since   1.4.0
	 */
	public function getTokensForUser(int $userId): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__api_tokens'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId))
			->order($db->quoteName('id') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}
}
