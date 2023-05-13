<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Container\Container;
use Awf\Mvc\Controller;

class Main extends Controller
{
	use ACLTrait;

	public function __construct(Container $container = null)
	{
		$this->modelName = 'site';

		parent::__construct($container);
	}

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}


	protected function onBeforeDefault(): bool
	{
		if ($this->input->get('savestate', -999, 'int') == -999)
		{
			$this->input->set('savestate', true);
		}

		return true;
	}
}