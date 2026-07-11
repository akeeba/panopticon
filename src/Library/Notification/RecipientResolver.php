<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Notification;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;

/**
 * Resolves the recipient user IDs for an outgoing notification (email, Web Push, or any
 * notification-channel plugin listening to onNotificationSend).
 *
 * @since  __DEPLOY_VERSION__
 */
class RecipientResolver
{
	/**
	 * Resolve the recipient user IDs for a notification, given the same data Registry used by
	 * EmailSendingTrait::enqueueEmail().
	 *
	 * @param   Registry  $data    The mail data registry (recipient_id, permissions, email_groups, ...)
	 * @param   int|null  $siteId  The site ID this notification refers to
	 *
	 * @return  int[]
	 * @since   __DEPLOY_VERSION__
	 */
	public function resolveUserIds(Registry $data, ?int $siteId): array
	{
		$recipientId = $data->get('recipient_id', null);

		if ($recipientId)
		{
			return [(int) $recipientId];
		}

		$permissions    = $data->get('permissions', []) ?? [];
		$permissions    = is_array($permissions) ? $permissions : [];
		$mailGroups     = $data->get('email_groups', null);
		$mailGroups     = empty($mailGroups) ? null : array_filter(ArrayHelper::toInteger($mailGroups));
		$onlyMailGroups = (bool) $data->get('only_email_groups', false);

		return $onlyMailGroups
			? $this->getRecipientUserIds([], null, $mailGroups)
			: $this->getRecipientUserIds($permissions, $siteId, $mailGroups);
	}

	/**
	 * Get the user IDs that match the given permissions, similar to SendMail::getRecipientsByPermissions
	 * but returning just user IDs instead of [email, name, params].
	 *
	 * @param   array       $permissions  The permissions to check
	 * @param   int|null    $siteId       The site ID for per-site permissions
	 * @param   array|null  $mailGroups   Additional mail groups
	 *
	 * @return  int[]  Array of user IDs
	 * @since   __DEPLOY_VERSION__
	 */
	private function getRecipientUserIds(array $permissions, ?int $siteId = null, ?array $mailGroups = null): array
	{
		$container = Factory::getContainer();
		$db        = $container->db;

		// If we have a site we need to find which groups it belongs to
		$groupIDs = [];

		if ($siteId !== null)
		{
			try
			{
				$site     = $container->mvcFactory->makeTempModel('Site');
				$site->findOrFail($siteId);
				$groupIDs = $site->getConfig()->get('config.groups', []);
				$groupIDs = is_array($groupIDs) ? $groupIDs : [];
				$groupIDs = array_filter(ArrayHelper::toInteger($groupIDs));
			}
			catch (\Exception)
			{
				// Site not found; continue without group filtering
			}
		}

		// If we have groups, find which fulfill the permissions
		if ($groupIDs)
		{
			$query = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__groups'))
				->where($db->quoteName('id') . ' IN(' . implode(',', $groupIDs) . ')');

			$query->andWhere(
				array_map(
					fn($permission) => 'JSON_SEARCH(' . $db->quoteName('privileges') . ', ' . $db->quote('one') . ',' . $db->quote($permission) . ')',
					$permissions
				)
			);

			$groupIDs = $db->setQuery($query)->loadColumn();
		}

		$groupIDs = array_unique(array_merge($groupIDs, $mailGroups ?? []));

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'));

		foreach ($permissions as $permission)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('parameters'), '$.acl.' . $permission) . ' = TRUE',
				'OR'
			);
		}

		foreach ($groupIDs as $groupID)
		{
			$query->where(
				$query->jsonContains(
					$db->quoteName('parameters'),
					$db->quote((string) $groupID),
					$db->quote('$.usergroups')
				), 'OR'
			);
		}

		return array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
	}
}
