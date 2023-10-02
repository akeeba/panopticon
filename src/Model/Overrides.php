<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Task\ApiRequestTrait;
use Awf\Mvc\Model;
use Awf\Pagination\Pagination;
use Awf\Registry\Registry;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\Utils\Collection;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;

/**
 * View Template Overrides Model
 *
 * @since  1.0.0
 */
class Overrides extends Model
{
	use ApiRequestTrait;

	private ?Site $site = null;

	public function setSite(Site $site): self
	{
		$this->site = $site;

		return $this;
	}

	public function count(): int
	{
		$config = $this->site?->getConfig() ?? new Registry();

		return (int) $config->get('core.overridesChanged', 0);
	}

	public function getPagination(): Pagination
	{
		$limitStart = $this->getUserStateFromRequest('limitstart', 'limitstart', 0, 'int') ?: 0;
		$limit      = $this->getUserStateFromRequest('limit', 'limit', 20, 'int') ?: 20;

		return new Pagination($this->count(), $limitStart, $limit, 10, $this->getContainer());
	}

	public function get(): Collection
	{
		if (empty($this->site))
		{
			return new Collection();
		}

		$limitStart = $this->getUserStateFromRequest('limitstart', 'limitstart', 0, 'int') ?: 0;
		$limit      = $this->getUserStateFromRequest('limit', 'limit', 20, 'int') ?: 20;
		$client     = $this->getUserStateFromRequest('client', 'client', 0, 'int') ?: 0;

		[$url, $options] = $this->getRequestOptions($this->site, '/index.php/v1/panopticon/template/overrides/changed');

		$uri = new Uri($url);
		$uri->setVar('client', $client);
		$uri->setVar('page[limit]', $limit);
		$uri->setVar('page[offset]', $limitStart);

		/** @var \Akeeba\Panopticon\Container $container */
		$container  = $this->container;
		$httpClient = $container->httpFactory->makeClient(cache: true, cacheTTL: 10);

		$response = $httpClient->get($uri->toString(), $options);

		try
		{
			$rawData = @json_decode($response->getBody()->getContents());
		}
		catch (\Exception $e)
		{
			$rawData = null;
		}

		if (empty($rawData) || !is_object($rawData) || !is_array($rawData?->data ?? null) || empty($rawData?->data ?? null))
		{
			return new Collection();
		}

		return new Collection(
			array_filter(
				array_map(
					function ($item): ?object {
						if (!is_object($item) || empty($item->attributes ?? null) || !is_object($item->attributes ?? null))
						{
							return null;
						}

						return $item->attributes;
					},
					$rawData->data
				)
			)
		);
	}

	public function getIdFieldName(): string
	{
		return 'id';
	}

	public function getItem(): ?object
	{
		$id = $this->getState('id');

		if (empty($this->site) || empty($id) || intval($id) <= 0)
		{
			return null;
		}

		$relativeUrl = sprintf('/index.php/v1/panopticon/template/overrides/changed/%d', $id);
		[$url, $options] = $this->getRequestOptions($this->site, $relativeUrl);

		/** @var \Akeeba\Panopticon\Container $container */
		$container  = $this->container;
		$httpClient = $container->httpFactory->makeClient(cache: true, cacheTTL: 10);

		try
		{
			$response = $httpClient->get($url, $options);
		}
		catch (\Throwable $e)
		{
			return $e;
		}

		try
		{
			$rawData = @json_decode($response->getBody()->getContents());
		}
		catch (\Exception $e)
		{
			return null;
		}

		$data = $rawData?->data?->attributes;

		if (empty($data))
		{
			return null;
		}

		$diffOptions = [
			'context'          => Differ::CONTEXT_ALL,
			'ignoreCase'       => false,
			'ignoreLineEnding' => false,
			'ignoreWhitespace' => false,
			'lengthLimit'      => 2000,
		];

		$rendererOptions = [
			'detailLevel'    => 'line',
			'language'       => [
				"old_version" => Text::_('PANOPTICON_OVERRIDES_LBL_CORE'),
				"new_version" => Text::_('PANOPTICON_OVERRIDES_LBL_OVERRIDE'),
				"differences" => Text::_('PANOPTICON_OVERRIDES_LBL_DIFF'),
			],
			'lineNumbers'    => true,
			'separateBlock'  => false,
			'showHeader'     => true,
			'spacesToNbsp'   => false,
			'tabSize'        => 4,
			'wrapperClasses' => ['diff-wrapper'],
		];

		$data->diff = DiffHelper::calculate($data->coreSource, $data->overrideSource, 'SideBySide', $diffOptions, $rendererOptions);

		return $data;
	}
}