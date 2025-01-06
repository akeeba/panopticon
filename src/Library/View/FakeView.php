<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\View;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\View\Trait\TimeAgoTrait;
use Awf\Mvc\View;

class FakeView extends View
{
	use TimeAgoTrait;

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

	public function getViewFinder()
	{
		return $this->viewFinder;
	}
}