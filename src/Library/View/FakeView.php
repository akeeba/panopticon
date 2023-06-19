<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\View;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Awf\Mvc\View;

class FakeView extends View
{
	/**
	 * @param   Container  $container
	 * @param   array      $config
	 */
	public function __construct($container = null, array $config = [])
	{
		$this->name           = $config['name'];
		$container            = clone $container;
		$container->mvcConfig = $config;

		parent::__construct($container);
	}

}