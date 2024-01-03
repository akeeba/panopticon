<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Usagestats;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Usagestats;
use Awf\Date\Date;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Exception;
use Throwable;

class Html extends BaseHtmlView
{
	protected bool $isCollectionEnabled = false;

	protected ?array $data;

	protected string $serverUrl;

	protected ?Date $lastCollectionDate;

	public function onBeforeMain(): bool
	{
		$toolbar = $this->getContainer()->application->getDocument()->getToolbar();

		$toolbar->setTitle($this->getLanguage()->text('PANOPTICON_USAGESTATS_TITLE'));

		/** @var Usagestats $model */
		$model = $this->getModel();

		try
		{
			$this->isCollectionEnabled = $model->isStatsCollectionEnabled();
		}
		catch (Throwable)
		{
			$this->isCollectionEnabled = false;
		}

		try
		{
			$this->data = $model->getData();
		}
		catch (Throwable)
		{
			$this->data = null;
		}

		try
		{
			$this->serverUrl = $model->getServerUrl();
		}
		catch (Exception)
		{
			$this->serverUrl = 'https://abrandnewsite.com/index.php';
		}

		try
		{
			$this->lastCollectionDate = $model->getLastCollectionDate();
		}
		catch (Throwable)
		{
			$this->lastCollectionDate = null;
		}

		return true;
	}
}