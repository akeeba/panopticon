<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\About;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\About;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	protected ?array $contributors;

	protected array $npmInfo;

	protected array $dependencies;

	protected function onBeforeMain(): bool
	{
		$this->addButton('back', ['url' => 'javascript:history.back()']);
		$this->setTitle($this->getLanguage()->text('PANOPTICON_ABOUT_TITLE'));

		/** @var About $model */
		$model              = $this->getModel();
		$this->contributors = $model->getContributors();
		$this->npmInfo      = $model->getNPMInformation();
		$this->dependencies  = $model->getDependencies();

		return true;
	}
}