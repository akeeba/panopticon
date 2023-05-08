<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Container\Container;
use Awf\Date\Date;
use Awf\Mvc\DataModel;
use Awf\Registry\Registry;
use Awf\Text\Text;
use Awf\Uri\Uri;
use RuntimeException;

/**
 * Defines a site known to Panopticon
 *
 * @property int       $id              Task ID.
 * @property string    $name            The name of the site (user-visible).
 * @property string    $url             The URL to the site (with the /api part).
 * @property int       $enabled         Is this site enabled?
 * @property Date      $created_on      When was this site created?
 * @property int       $created_by      Who created this site?
 * @property null|Date $modified_on     When was this site last modified?
 * @property null|int  $modified_by     Who last modified this site?
 * @property null|Date $locked_on       When was this site last locked for writing?
 * @property null|int  $locked_by       Who last locked this site for writing?
 * @property Registry  $config          The configuration for this site.
 *
 * @since  1.0.0
 */
class Site extends DataModel
{
	public function __construct(Container $container = null)
	{
		$this->tableName   = '#__sites';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	public function check()
	{
		parent::check();

		$this->name = trim($this->name ?? '');

		if (empty($this->name))
		{
			throw new RuntimeException(Text::_('PANOPTICON_SITES_ERR_NO_TITLE'));
		}

		if (empty($this->url))
		{
			throw new RuntimeException(Text::_('PANOPTICON_SITES_ERR_NO_URL'));
		}

		$this->url = $this->cleanUrl($this->url);

		return $this;
	}

	private function cleanUrl(?string $url): string
	{
		$url = trim($url ?? '');
		$uri = new Uri($url);

		if (!in_array($uri->getScheme(), ['http', 'https']))
		{
			$uri->setScheme('http');
		}

		$uri->setQuery('');
		$uri->setFragment('');
		$path = rtrim($uri->getPath(), '/');

		if (str_ends_with($path, '/api/index.php'))
		{
			$path = substr($path, 0, -10);
		}

		if (str_contains($path, '/api/'))
		{
			$path = substr($path, 0, strrpos($path, '/api/')) . '/api';
		}

		if (!str_ends_with($path, '/api'))
		{
			$path .= '/api';
		}

		$uri->setPath($path);

		return $uri->toString();
	}

}