<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\Controller;
use Awf\Text\Text;

class Selfupdate extends Controller
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	public function onBeforeDefault(): bool
	{
		$force = $this->input->getInt('force', false);

		$this->getView()->force = $force;

		return true;
	}

	public function update()
	{
		/** @var \Akeeba\Panopticon\Model\Selfupdate $model */
		$model = $this->getModel();

		try
		{
			$targetFile = $model->download();
		}
		catch (\Exception $e)
		{
			$url = $this->container->router->route('index.php?view=selfupdate');

			$this->setRedirect(
				$url,
				Text::_('PANOPTICON_SELFUPDATE_ERR_DOWNLOADFAILED') . ' ' . $e->getMessage()
			);
		}

		$this->container->segment->setFlash('selfupdate.localfile', $targetFile);

		$url = $this->container->router->route('index.php?view=selfupdate&task=install');

		$this->setRedirect($url);

	}

	public function install()
	{
		/** @var \Akeeba\Panopticon\Model\Selfupdate $model */
		$model = $this->getModel();

		try
		{
			$sourceFile = $this->container->segment->getFlash('selfupdate.localfile');

			$targetFile = $model->extract($sourceFile);
		}
		catch (\Exception $e)
		{
			$url = $this->container->router->route('index.php?view=selfupdate');

			$this->setRedirect(
				$url,
				Text::_('PANOPTICON_SELFUPDATE_ERR_EXTRACTFAILED') . ' ' . $e->getMessage()
			);
		}

		$url = $this->container->router->route('index.php?view=selfupdate&task=postinstall');

		$this->setRedirect($url);
	}

	public function postinstall()
	{
		/** @var \Akeeba\Panopticon\Model\Selfupdate $model */
		$model = $this->getModel();

		try
		{
			$model->postUpdate();
		}
		catch (\Exception $e)
		{
			$url = $this->container->router->route('index.php?view=selfupdate');

			$this->setRedirect(
				$url,
				Text::_('PANOPTICON_SELFUPDATE_ERR_POSTINSTALLFAILED') . ' ' . $e->getMessage()
			);
		}

		$url = $this->container->router->route('index.php?view=selfupdate', Text::_('PANOPTICON_SELFUPDATE_LBL_SUCCESS'));

		$this->setRedirect($url);
	}
}