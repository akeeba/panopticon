<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Awf\Mvc\Controller;

class Usagestats extends Controller
{
	public function resetsid()
	{
		$this->csrfProtection();

		$this->getModel()->resetSID();

		$this->setRedirect($this->getContainer()->router->route('index.php?view=usagestats'));
	}

	public function disable()
	{
		$this->csrfProtection();

		$this->getModel()->setFeatureStatus(false);

		$this->setRedirect($this->getContainer()->router->route('index.php?view=usagestats'));
	}

	public function enable()
	{
		$this->csrfProtection();

		$this->getModel()->setFeatureStatus(true);

		$this->setRedirect($this->getContainer()->router->route('index.php?view=usagestats'));
	}

	public function send()
	{
		$this->csrfProtection();

		$this->getModel()->collectStatistics(true);

		$this->setRedirect($this->getContainer()->router->route('index.php?view=usagestats'));
	}

	public function ajax()
	{
		$this->getModel()->collectStatistics(false);

		$this->getContainer()->application->close();
	}
}