<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;


use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;

defined('AKEEBA') || die;

class Reports extends Controller
{
	use ACLTrait;

	public function test()
	{
		$this->display();
	}
}