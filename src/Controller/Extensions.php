<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;


use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;

defined('AKEEBA') || die;

class Extensions extends Controller
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function main()
	{
		$view      = $this->getView();
		$siteModel = $this->getModel('site');
		$view->setModel('site', $siteModel);

		// When no group filter is selected we are POSTed no value. In this case, we need to unset the filter.
		if (strtoupper($this->input->getMethod() ?? '') === 'POST')
		{
			$groups = $this->input->post->getRaw('group');

			if ($groups === null)
			{
				$this->input->set('group', []);
			}
		}

		parent::main();
	}
}
