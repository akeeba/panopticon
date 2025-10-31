<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Tasks;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Registry as TaskRegistry;
use Akeeba\Panopticon\Model\Task;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Utils\ArrayHelper;
use Awf\Utils\Template;

class Html extends BaseHtmlView
{
	use CrudTasksTrait
	{
		onBeforeBrowse as onBeforeBrowseCrud;
	}

	/**
	 * @var array|mixed
	 */
	protected array $siteNames;

	public function onBeforeBrowse()
	{
		$return = $this->onBeforeBrowseCrud();

		$this->container->application->getDocument()->getToolbar()->clearButtons();

		$this->addButton('delete');
		$this->addButton('publish');
		$this->addButton('unpublish');

		$this->populateSiteNames();

		Template::addJs('media://js/remember-tab.js', $this->getContainer()->application);

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    });

JS;
		$this->container->application->getDocument()->addScriptDeclaration($js);

		return $return;
	}

	protected function getTaskTypeOptions(): array
	{
		/** @var TaskRegistry $registry */
		$registry = $this->container->taskRegistry;
		$return   = [];

		/** @var CallbackInterface $cb */
		foreach ($registry->getIterator() as $cb)
		{
			$return[$cb->getTaskType()] = $cb->getDescription();
		}

		return $return;
	}

	private function populateSiteNames(): void
	{
		$this->siteNames = [];

		if ($this->itemsCount <= 0)
		{
			return;
		}

		$siteIDs = $this->items->map(fn(Task $task) => $task->site_id)->toArray();
		$siteIDs = array_unique(array_filter($siteIDs));
		$siteIDs = ArrayHelper::toInteger($siteIDs);

		if (empty($siteIDs))
		{
			return;
		}

		$db              = $this->container->db;
		$query           = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('name'),
			])->from($db->quoteName('#__sites'))
			->where($db->quoteName('id') . ' IN (' . implode(',', $siteIDs) . ')');
		$this->siteNames = $db->setQuery($query)->loadAssocList('id', 'name') ?: [];
	}
}