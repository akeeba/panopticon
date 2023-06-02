<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Container\Container;
use Awf\Mvc\DataModel;
use Awf\Text\Text;
use RuntimeException;

/**
 * @property int    $id
 * @property string $username
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $parameters
 */
class Users extends DataModel
{
	use UserAvatarTrait;

	public function __construct(Container $container = null)
	{
		$this->tableName   = '#__users';
		$this->idFieldName = 'id';

		parent::__construct($container);

		//$this->addBehaviour('filters');
	}

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$search = trim($this->getState('search', null) ?? '');

		if (!empty($search))
		{
			$query->extendWhere('AND', [
				$query->quoteName('username') . ' LIKE ' . $query->quote('%' . $search . '%'),
				$query->quoteName('name') . ' LIKE ' . $query->quote('%' . $search . '%'),
				$query->quoteName('email') . ' LIKE ' . $query->quote('%' . $search . '%'),
			], 'OR');
		}

		return $query;
	}

	public function getGroupsForSelect(): array
	{
		$db    = $this->getDbo();
		$query = $db
			->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
			])
			->from($db->quoteName('#__groups'));

		return array_map(fn($x) => $x->title, $db->setQuery($query)->loadObjectList('id') ?: []);
	}

	protected function onBeforeDelete($id): void
	{
		$mySelf = $this->container->userManager->getUser();

		// Cannot delete myself
		if ($id == $mySelf->getId())
		{
			throw new RuntimeException(Text::_('PANOPTICON_USERS_ERR_CANT_DELETE_YOURSELF'), 403);
		}

		// Cannot delete the last Superuser
		if ($this->isLastSuperUserAccount($id))
		{
			// Normally this should've been caught by "cannot delete myself", but being overly cautious never hurt anyone.
			throw new RuntimeException('PANOPTICON_USERS_ERR_CANT_DELETE_LAST_SUPER', 403);
		}
	}

	private function isLastSuperUserAccount(int $id): bool
	{
		$thatUser = $this->container->userManager->getUser($id);

		if ($thatUser->getId() != $id || !$thatUser->getPrivilege('panopticon.super'))
		{
			return false;
		}

		$db    = $this->getDbo();
		$query = $db
			->getQuery(true)
			->select('COUNT(*)')
			->from('#__users')
			->where($db->quoteName('id') . ' != ' . $db->quote((int) $id));
		$query->where($query->jsonPointer('parameters', '$.acl.panopticon.super') . ' = TRUE');

		$howManySuperUsersLeft = $db->setQuery($query)->loadResult() ?: 0;

		return $howManySuperUsersLeft < 1;
	}
}