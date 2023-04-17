<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\View\Updates;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\MVC\View\JsonApiView as BaseJsonApiView;
use Joomla\CMS\Uri\Uri;
use Tobscure\JsonApi\Collection;

class JsonapiView extends BaseJsonApiView
{
	/**
	 * Execute and display a template script.
	 *
	 * @param   array|null  $items  Array of items
	 *
	 * @return  string
	 *
	 * @since   4.0.0
	 */
	public function displayList(array $items = null)
	{
		// Get page query
		$currentUrl                    = Uri::getInstance();
		$currentPageDefaultInformation = ['offset' => 0, 'limit' => 20];
		$currentPageQuery              = $currentUrl->getVar('page', $currentPageDefaultInformation);

		if ($items === null) {
			$items = [];
		}

		if ($this->type === null) {
			throw new \RuntimeException('Content type missing');
		}

		$this->document->addMeta('total-pages', 1)
			->addLink('self', (string) $currentUrl);

		$collection = (new Collection($items, $this->serializer))
			->fields([$this->type => [
				'status',
				'messages',
			]]);

		// Set the data into the document and render it
		$this->document->setData($collection);

		return $this->document->render();
	}

}