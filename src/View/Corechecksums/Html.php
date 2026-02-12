<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Corechecksums;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Corechecksums;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	public Site $site;

	public array $modifiedFiles = [];

	public ?int $lastCheck = null;

	public ?bool $lastStatus = null;

	public function onBeforeBrowse()
	{
		$this->addButton(
			'back',
			[
				'url' => $this->container->router->route(
					sprintf(
						'index.php?view=site&task=read&id=%d',
						$this->site->id
					)
				),
			]
		);
		$this->setTitle($this->getLanguage()->text('PANOPTICON_CORECHECKSUMS_TITLE'));

		/** @var Corechecksums $model */
		$model = $this->getModel();
		$model->setSite($this->site);

		$this->modifiedFiles = $model->getModifiedFiles();
		$this->lastCheck     = $model->getLastCheck();
		$this->lastStatus    = $model->getLastStatus();

		return true;
	}
}
