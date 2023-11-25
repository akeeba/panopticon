<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\User;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Trait\UserAvatarTrait;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Registry\Registry;

class User extends \Awf\User\User implements ContainerAwareInterface
{
	use UserAvatarTrait;
	use ContainerAwareTrait;

	private array $groupPrivileges = [];

	public function bind(&$data)
	{
		parent::bind($data);

		$this->groupPrivileges = $this->loadGroupPrivileges();
	}

	public function getPrivilege($privilege, $default = false)
	{
		$result = parent::getPrivilege($privilege, $default);

		// The panopticon.super privilege magically grants you all other privileges
		if ($privilege !== 'panopticon.super')
		{
			return $result || $this->getPrivilege('panopticon.super', false);
		}

		return $result;
	}

	public function authorise(string $privilege, int|Site $site): bool
	{
		// Am I a Super User, or have this privilege globally?
		if ($this->getPrivilege($privilege))
		{
			return true;
		}

		// If I have a site ID let me grab the actual site object
		if (is_int($site))
		{
			try
			{
				$site = $this->getContainer()->mvcFactory->makeTempModel('Site')
					->findOrFail($site);
			}
			catch (\Exception $e)
			{
				return false;
			}
		}

		// Get the user groups for this site
		$config = ($site->config instanceof Registry)
			? $site->config
			: new Registry($site->config ?? '{}');

		$groupIDs = $config->get('config.groups', []) ?: [];

		if (empty($groupIDs))
		{
			return false;
		}

		// Evaluate user group privileges
		foreach ($groupIDs as $gid)
		{
			if (!isset($this->groupPrivileges[$gid]))
			{
				continue;
			}

			if (in_array($privilege, $this->groupPrivileges[$gid]))
			{
				return true;
			}
		}

		return false;
	}

	public function getGroupPrivileges(): array
	{
		return $this->groupPrivileges;
	}

	private function loadGroupPrivileges(): array
	{
		$db       = Factory::getContainer()->db;
		$groupIDs = implode(
			',',
			array_map(
				[$db, 'quote'],
				$this->getParameters()->get('usergroups', [])
			)
		);

		if (empty($groupIDs))
		{
			return [];
		}

		$query    = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('privileges'),
			])
			->from($db->quoteName('#__groups'))
			->where($db->quoteName('id') . ' IN(' . $groupIDs . ')');

		return array_map(
			fn(object $x) => json_decode($x->privileges),
			$db->setQuery($query)->loadObjectList('id') ?: []
		);
	}
}