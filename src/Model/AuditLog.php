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
 * Model for the application-wide audit log.
 *
 * @property int         $id          Record ID.
 * @property Date        $occurred_on When the event happened.
 * @property int|null    $user_id     User responsible for the event, NULL for anonymous/system.
 * @property string|null $ip          Client IP as a packed (inet_pton) binary string.
 * @property string      $action      Stable machine-readable action identifier (e.g. apitoken.create).
 * @property string|null $target_type Type of object the action targets (e.g. "apitoken").
 * @property int|null    $target_id   ID of the target object, if any.
 * @property string|null $details     JSON-encoded extra context.
 *
 * @since  1.4.0
 */
class AuditLog extends DataModel
{
	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__audit_log';
		$this->idFieldName = 'id';

		parent::__construct($container);
	}

	/**
	 * Record an audit event. Failures are swallowed: audit logging must never break a request.
	 *
	 * @param   string       $action      Action identifier (e.g. "apitoken.create").
	 * @param   int|null     $userId      User responsible, or NULL.
	 * @param   string|null  $ipBinary    Client IP as a packed (inet_pton) binary string, or NULL.
	 * @param   string|null  $targetType  Target object type.
	 * @param   int|null     $targetId    Target object ID.
	 * @param   array|null   $details     Extra context to be JSON-encoded.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public static function record(
		string $action,
		?int $userId = null,
		?string $ipBinary = null,
		?string $targetType = null,
		?int $targetId = null,
		?array $details = null
	): void
	{
		try
		{
			$container = Factory::getContainer();
			$db        = $container->db;

			$detailsJson = ($details === null || $details === [])
				? null
				: json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			$columns = [
				$db->quoteName('user_id'),
				$db->quoteName('ip'),
				$db->quoteName('action'),
				$db->quoteName('target_type'),
				$db->quoteName('target_id'),
				$db->quoteName('details'),
			];

			$values = [
				$userId === null ? 'NULL' : $db->quote($userId),
				$ipBinary === null ? 'NULL' : $db->quote($ipBinary),
				$db->quote($action),
				$targetType === null ? 'NULL' : $db->quote($targetType),
				$targetId === null ? 'NULL' : $db->quote($targetId),
				$detailsJson === null ? 'NULL' : $db->quote($detailsJson),
			];

			$query = $db->getQuery(true)
				->insert($db->quoteName('#__audit_log'))
				->columns($columns)
				->values(implode(', ', $values));

			$db->setQuery($query)->execute();
		}
		catch (\Throwable)
		{
			// Audit logging is best-effort.
		}
	}
}
