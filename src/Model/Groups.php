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

/**
 * Handle user groups
 *
 * @property int    $id         The group's ID
 * @property string $title      The group's title
 * @property string $privileges JSON-encoded list of privileges
 */
class Groups extends DataModel
{
	public function __construct(Container $container = null)
	{
		$this->tableName   = '#__groups';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	public function getPrivileges(): array
	{
		return is_array($this->privileges)
			? $this->privileges
			: (json_decode($this->privileges ?: '[]') ?: []);
	}

	public function setPrivileges(array $privileges): void
	{
		$privileges = array_values($privileges);
		$privileges = array_filter($privileges, fn($x) => in_array($x, ['panopticon.view', 'panopticon.run', 'panopticon.admin']));

		$this->privileges = json_encode($privileges);
	}
}