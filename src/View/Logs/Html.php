<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Logs;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Log;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Inflector\Inflector;
use Awf\Mvc\DataView\Html as BaseHtml;
use Awf\Text\Text;
use Awf\Utils\ArrayHelper;
use Awf\Utils\Template;

/**
 * Log management view
 *
 * @since  1.0.0
 */
class Html extends BaseHtml
{
	use CrudTasksTrait;

	protected array $siteNames = [];

	protected array $logLines;

	protected int $fileSize;

	public function onBeforeMain(): bool
	{
		$this->setTitle(Text::_('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE'));

		// If no list limit is set, use the Panopticon default (50) instead of All (AWF's default).
		$limit = $this->getModel()->getState('limit', 50, 'int');
		$this->getModel()->setState('limit', $limit);

		/** @var Log $model */
		$model = $this->getModel();

		$this->items      = $model->getPaginatedLogFiles();
		$this->pagination = $model->getPagination();

		$this->populateSiteNames();

		return true;
	}

	public function onBeforeRead(): bool
	{
		$this->setTitle(Text::_('PANOPTICON_' . Inflector::pluralize($this->getName()) . '_TITLE_READ'));
		$this->addButton('back', ['url' => $this->container->router->route('index.php?view=logs')]);

		/** @var Log $model */
		$model          = $this->getModel();
		$this->logLines = $model->getLogLines();
		$this->fileSize = $model->getFilesize();
		$this->filePath = $model->getVerifiedLogFilePath();

		if (empty($this->fileSize))
		{
			$this->setLayout('item_none');
		}

		$document = $this->container->application->getDocument();
		$document->addScriptOptions('log', [
			'url' => $this->container->router->route(sprintf('index.php?view=log&task=read&logfile=%s&format=raw', urlencode(basename($this->filePath))))
		]);

		Template::addJs('media://js/log.js', $this->container->application, defer: true);

		return true;
	}

	protected function filesize($fileName): string
	{
		return $this->formatFilesize($this->getModel()->getFilesize($fileName));
	}

	/**
	 * Converts number of bytes to a human-readable representation.
	 *
	 * @param   int|null  $sizeInBytes         Size in bytes
	 * @param   int       $decimals            How many decimals should I use? Default: 2
	 * @param   string    $decSeparator        Decimal separator
	 * @param   string    $thousandsSeparator  Thousands grouping character
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	protected function formatFilesize(
		?int $sizeInBytes, int $decimals = 2, string $decSeparator = '.', string $thousandsSeparator = ''
	): string
	{
		if ($sizeInBytes <= 0)
		{
			return '&mdash;';
		}

		$units = ['b', 'KiB', 'MiB', 'GiB', 'TiB'];
		$unit  = floor(log($sizeInBytes, 2) / 10);

		if ($unit == 0)
		{
			$decimals = 0;
		}

		return number_format($sizeInBytes / (1024 ** $unit), $decimals, $decSeparator, $thousandsSeparator) . ' '
		       . $units[$unit];
	}

	protected function getSiteIdFromFilename($logFilename): ?int
	{
		[$prefix,] = explode('.log', $logFilename);

		if (empty($prefix) || !str_contains($prefix, '.'))
		{
			return null;
		}

		$parts = explode('.', $prefix);

		if (count($parts) === 1)
		{
			return null;
		}

		$lastPart = array_pop($parts);

		if (!is_numeric($lastPart))
		{
			return null;
		}

		return intval($lastPart);
	}

	private function populateSiteNames(): void
	{
		$this->siteNames = [];

		if ($this->itemsCount <= 0)
		{
			return;
		}

		$siteIDs = array_map([$this, 'getSiteIdFromFilename'], $this->items);
		$siteIDs = array_filter($siteIDs);
		$siteIDs = array_unique(array_filter($siteIDs));
		$siteIDs = ArrayHelper::toInteger($siteIDs);

		if (empty($siteIDs))
		{
			return;
		}

		$db              = $this->container->db;
		$query           = $db->getQuery(true)->select(
				[
					$db->quoteName('id'),
					$db->quoteName('name'),
				]
			)->from($db->quoteName('#__sites'))->where($db->quoteName('id') . ' IN (' . implode(',', $siteIDs) . ')');
		$this->siteNames = $db->setQuery($query)->loadAssocList('id', 'name') ?: [];
	}
}