<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\About;

defined('AKEEBA') || die;

use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	protected ?array $contributors;

	protected function onBeforeMain(): bool
	{
		$this->addButton('back', ['url' => 'javascript:history.back()']);
		$this->setTitle($this->getLanguage()->text('PANOPTICON_ABOUT_TITLE'));

		$this->contributors = $this->getModel()->getContributors();

		return true;
	}
}