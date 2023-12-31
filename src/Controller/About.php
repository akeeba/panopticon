<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;

defined('AKEEBA') || die;

class About extends Controller
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

}