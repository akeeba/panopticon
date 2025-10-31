<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\SiteInfo;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Container\Container;
use Awf\Container\ContainerAwareTrait;
use Awf\Uri\Uri;
use DOMDocument;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Site Information Retriever
 *
 * A small helper class to retrieve the name and favicon of a site by parsing its home page URL.
 *
 * @since  1.1.0
 */
class Retriever
{
	use ContainerAwareTrait;

	/**
	 * List of all possible icon definitions.
	 *
	 * @var    array
	 * @since  1.1.0
	 */
	private array $icons = [];

	/**
	 * The detected biggest available icon
	 *
	 * @var    string|null
	 * @since  1.1.0
	 */
	private ?string $bestIcon = null;

	/**
	 * The title of the HTML document
	 *
	 * @var    string|null
	 * @since  1.1.0
	 */
	private ?string $title = null;

	/**
	 * The HTML of the page.
	 *
	 * @var    string|null
	 * @since  1.1.0
	 */
	private ?string $html = null;

	/**
	 * Public constructor
	 *
	 * @param   Container  $container    The application container
	 * @param   string     $url          The URL to the page to retrieve favicons for
	 * @param   bool       $forceReload  Should I forcibly reload the page contents?
	 *
	 * @since   1.1.0
	 */
	public function __construct(protected $container, private string $url, private readonly bool $forceReload = false)
	{
		$this->fetchHtml();
		$this->processHtml();
		$this->getBiggestIcon();
	}

	/**
	 * Return all discovered icon definitions (plus the default favicon locations)
	 *
	 * @return  array
	 * @since   1.1.0
	 */
	public function getAllIconDefinitions(): array
	{
		return $this->icons;
	}

	/**
	 * Return all icon URLs, in descending order of reported sizes.
	 *
	 * @return  array
	 * @since   1.1.0
	 */
	public function getAllIconURLs(): array
	{
		return array_map(fn($item) => $item->url, $this->icons);
	}

	/**
	 * Get the biggest available favicon we can successfully download.
	 *
	 * @param   bool  $testDownload  Should I try to download the icon to make sure it actually works?
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	public function getBiggestIcon(bool $testDownload = true): ?string
	{
		if (empty($this->icons))
		{
			return null;
		}

		$this->bestIcon ??= $this->getIconUrl(testDownload: $testDownload);

		return $this->bestIcon;
	}

	/**
	 * Get a favicon URL matching the criteria.
	 *
	 * @param   int          $minSize       Minimum dimension in pixels
	 * @param   string|null  $type          Preferred type: ico, png, jpg, svg, gif
	 * @param   bool         $testDownload  Should I test the icon file can be downloaded?
	 *
	 * @return  string|null
	 */
	public function getIconUrl(int $minSize = 0, ?string $type = null, bool $testDownload = false, bool $asDataUrl = false): ?string
	{
		foreach ($this->icons as $def)
		{
			if (!empty($type) && $def->type !== $type)
			{
				continue;
			}

			if ($def->size < $minSize)
			{
				continue;
			}

			if ($testDownload || $asDataUrl)
			{
				/** @var \Akeeba\Panopticon\Container $container */
				$container = $this->getContainer();

				if (str_starts_with((string) $def->url, 'data:'))
				{
					return $def->url;
				}

				$client = $container->httpFactory->makeClient(cache: false, singleton: false);

				try
				{
					$response = $client->get($def->url, $container->httpFactory->getDefaultRequestOptions());
					$data     = $response->getStatusCode() === 200 ? $response->getBody()->getContents() : null;
				}
				catch (GuzzleException)
				{
					$data = null;
				}

				if (empty($data))
				{
					continue;
				}

				if ($asDataUrl)
				{
					$mime = $this->getMimeTypeFromExtension($def->type);

					return sprintf("data:%s;base64,%s", $mime, base64_encode($data));
				}
			}

			return $def->url;
		}

		return null;
	}

	/**
	 * Get the title of the HTML document.
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function __serialize(): array
	{
		return [
			'url'      => $this->url,
			'html'     => $this->html,
			'icons'    => $this->icons,
			'bestIcon' => $this->bestIcon,
			'title'    => $this->title,
		];
	}

	public function __unserialize(array $data): void
	{
		$this->url      = $data['url'] ?? null;
		$this->html     = $data['html'] ?? null;
		$this->icons    = $data['icons'] ?? null;
		$this->bestIcon = $data['bestIcon'] ?? null;
		$this->title    = $data['title'] ?? null;

		$this->setContainer(Factory::getContainer());
	}

	/**
	 * Make sure we have some HTML loaded
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	private function fetchHtml(): void
	{
		if (!empty($this->html))
		{
			return;
		}

		/** @var \Akeeba\Panopticon\Container $container */
		$container = $this->getContainer();

		$client = $container->httpFactory->makeClient(cache: !$this->forceReload, cacheTTL: 7776000, singleton: false);

		try
		{
			$response   = $client->get($this->url, $container->httpFactory->getDefaultRequestOptions());
			$this->html = $response->getStatusCode() === 200 ? $response->getBody()->getContents() : null;
		}
		catch (GuzzleException)
		{
			$this->html = null;
		}
	}

	/**
	 * Process the HTML of the page and find the possible favicon URLs.
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	private function processHtml(): void
	{
		if (empty($this->html))
		{
			return;
		}

		// Start parsing the HTML
		$dom        = new DOMDocument();
		$errorLevel = error_reporting(0);
		$dom->loadHTML($this->html);
		error_reporting($errorLevel);

		// Do we have a title?
		$titleTags = $dom->getElementsByTagName('title');

		foreach ($titleTags as $tag)
		{
			$this->title ??= $tag->nodeValue;
		}

		// Get the site's base path: auto-detected, or from the HTML source.
		$baseHref = $this->getBaseHref($this->url);
		$baseTags = $dom->getElementsByTagName('base');

		foreach ($baseTags as $baseTag)
		{
			$baseHref = $baseTag->attributes['href']->value ?? $baseHref;
		}

		if (!empty($baseHref))
		{
			$baseHref = rtrim((string) $baseHref, '/') . '/';
			$scheme   = (new Uri($baseHref))->getScheme();
		}
		else
		{
			$scheme = 'http';
		}

		// Extract icons from link tags
		$icons    = [];
		$linkTags = $dom->getElementsByTagName('link');

		foreach ($linkTags as $linkTag)
		{
			$rel   = $linkTag->attributes['rel']?->value;
			$href  = $linkTag->attributes['href']?->value;
			$type  = $linkTag->attributes['type']?->value;
			$sizes = $linkTag->attributes['sizes']?->value;

			$maxSize = $this->parseSizes($sizes);

			if (!in_array($rel, ['icon', 'shortcut icon', 'apple-touch-icon', 'apple-touch-icon-precomposed'])
			    || empty($href))
			{
				continue;
			}

			switch ($this->urlType($href))
			{
				case UrlType::ABSOLUTE:
					$iconUrl  = $href;
					$iconType = $this->getExtension($iconUrl);
					break;

				case UrlType::ABSOLUTE_SCHEME:

					$iconUrl  = $scheme . ':' . $href;
					$iconType = $this->getExtension($iconUrl);
					break;

				case UrlType::ABSOLUTE_PATH:
					$iconUrl = rtrim($this->url, '/') . '/' . ltrim((string) $href, '/');

					if (!empty($baseHref))
					{
						$uri     = new Uri(
							$this->urlType($baseHref) === UrlType::ABSOLUTE ? $baseHref : $this->url
						);
						$iconUrl = rtrim($uri->toString(['scheme', 'user', 'pass', 'host', 'port']), '/') . '/' . ltrim(
								(string) $href, '/'
							);
					}

					$iconType = $this->getExtension($iconUrl);
					break;

				case UrlType::RELATIVE:
					$uri  = new Uri($this->url);
					$path = preg_replace('#/[^/]+?$#i', '/', $uri->getPath());

					$iconUrl = rtrim($uri->toString(['scheme', 'user', 'pass', 'host', 'port']), '/') . '/' . trim(
							(string) $path, '/'
						) . '/' . ltrim((string) $href, '/');

					if (!empty($baseHref))
					{
						$iconUrl = rtrim($baseHref, '/') . '/' . ltrim((string) $href, '/');
					}

					$iconType = $this->getExtension($iconUrl);
					break;
				case UrlType::EMBED_BASE64:
					// Format is data:mediatype;base64,data or data:mediatype,data
					$iconUrl = $href;
					[$descriptor,] = explode(',', (string) $iconUrl, 2);
					[, $descriptor] = explode(':', $descriptor, 2);
					// At this point I have either the bare media type or mediatype;base64.
					[$mediatype,] = explode(';', $descriptor, 2);
					$iconType = $this->getExtensionFromMimeType($mediatype);
					break;

				default:
					// Unknown type
					continue 2;
			}

			// If there is a type we're going to use it instead of the auto-detected icon type.
			if (!empty($type))
			{
				$iconType = $this->getExtensionFromMimeType($type);
			}

			$iconType = strtolower($iconType);

			$icons[] = (object) [
				'url'  => $iconUrl,
				'type' => $iconType,
				'size' => $maxSize,
			];
		}

		// Add the default icons, if we have a base URL
		if (!empty($baseHref))
		{
			$icons[] = (object) [
				'url'  => rtrim($baseHref, '/') . '/apple-touch-icon-precomposed.png',
				'type' => 'png',
				'size' => -1,
			];
			$icons[] = (object) [
				'url'  => rtrim($baseHref, '/') . '/apple-touch-icon.png',
				'type' => 'png',
				'size' => -1,
			];
			$icons[] = (object) [
				'url'  => rtrim($baseHref, '/') . '/favicon.ico',
				'type' => 'ico',
				'size' => -1,
			];
		}

		// Sort icons by size, descending
		uasort(
			$icons, function ($a, $b) {
			if ($a->size === $b->size)
			{
				return $a->url <=> $b->url;
			}

			return $a->size <=> $b->size;
		}
		);

		$this->icons = array_reverse($icons);
	}

	/**
	 * Get the best-guess site base path given a URL
	 *
	 * @param   string  $url  The URL to derive the base path from
	 *
	 * @return  string|null  The guessed base path, null if none can be guessed.
	 * @since   1.1.0
	 */
	private function getBaseHref(string $url): ?string
	{
		$uri = new Uri($url);

		if (empty($uri->getHost()))
		{
			return null;
		}

		$path = $uri->getPath() ?? '';

		if (str_contains($path, 'index.php'))
		{
			$path = trim(substr($path, 0, strpos($path, 'index.php') - 1), '/');
		}

		$uri->setPath($path);

		return rtrim($uri->toString(['scheme', 'user', 'pass', 'host', 'port', 'path']), '/') . '/';
	}

	/**
	 * Parse the `sizes` attribute of the LINK tags
	 *
	 * @param   string|null  $sizes  The attribute's value
	 *
	 * @return  int  The biggest reported dimension in those sizes
	 * @since   1.1.0
	 */
	private function parseSizes(?string $sizes): int
	{
		if (empty($sizes))
		{
			return 0;
		}

		$sizes = explode(' ', $sizes);
		$sizes = array_map(trim(...), $sizes);
		$sizes = array_filter($sizes);

		$values = [];

		foreach ($sizes as $item)
		{
			if (!str_contains($item, 'x'))
			{
				if (is_numeric($item))
				{
					$values[] = intval($item);
				}

				continue;
			}

			$parts = explode('x', $item);

			if ($parts < 2 || !is_numeric($parts[0]) || !is_numeric($parts[1]))
			{
				continue;
			}

			$x = intval($parts[0]);
			$y = intval($parts[1]);

			$values[] = max($x, $y);
		}

		$values[] = 0;

		return max($values);
	}

	/**
	 * Find the type of URL we're dealing with.
	 *
	 * The possible return values are:
	 *  * `UrlType::ABSOLUTE` e.g. `http://www.domain.com/images/fav.ico`.
	 *  * `UrlType::ABSOLUTE_SCHEME` e.g. `//www.domain.com/images/fav.ico`.
	 *  * `UrlType::ABSOLUTE_PATH` e.g. `/images/fav.ico`.
	 *  * `UrlType::RELATIVE` e.g. `../images/fav.ico`.
	 *  * `UrlType::EMBED_BASE64` e.g. `data:image/x-icon;base64,AAABAA...`.
	 *
	 * @param   string  $url  The URL to check
	 *
	 * @return  UrlType|null  URL type
	 * @since   1.1.0
	 */
	private function urlType(string $url): ?UrlType
	{
		if (empty($url))
		{
			return null;
		}

		$urlInfo = parse_url($url);

		if (!empty($urlInfo['scheme']))
		{
			return $urlInfo['scheme'] === 'data' ? UrlType::EMBED_BASE64 : UrlType::ABSOLUTE;
		}

		if (preg_match('#^//#i', $url))
		{
			return UrlType::ABSOLUTE_SCHEME;
		}

		if (preg_match('#^/[^/]#i', $url))
		{
			return UrlType::ABSOLUTE_PATH;
		}

		return UrlType::RELATIVE;
	}

	/**
	 * Return the file extension of a URL or file path
	 *
	 * @param   string  $url
	 *
	 * @return  string
	 * @since   1.1.0
	 */
	private function getExtension(string $url): string
	{
		if (preg_match('#^(https?|ftp)#i', $url))
		{
			$url = (new Uri($url))->getPath();
		}

		return pathinfo($url)['extension'];
	}

	/**
	 * Return file extension from MIME type
	 *
	 * @param   string  $mimeType
	 *
	 * @return  string
	 * @since   1.1.0
	 */
	private function getExtensionFromMimeType(string $mimeType): string
	{
		$regExMap = [
			'ico' => '#image/(x-icon|ico|vnd\.microsoft\.icon)#i',
			'png' => '#image/png#i',
			'gif' => '#image/gif#i',
			'jpg' => '#image/jpe?g#i',
			'svg' => '#image/svg\+xml#i',
		];

		foreach ($regExMap as $extension => $regex)
		{
			if (preg_match($regex, $mimeType))
			{
				return $extension;
			}
		}

		return 'ico';
	}


	/**
	 * Get the MIME type from a file extension
	 *
	 * @param   string  $extension  The file extension, without a dot
	 *
	 * @return  string  The corresponding MIME type
	 * @since   1.1.0
	 */
	private function getMimeTypeFromExtension(string $extension): string
	{
		return match (strtolower($extension))
		{
			'ico' => 'image/ico',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'jpg' => 'image/jpg',
			'svg' => 'image/svg+xml',
			default => 'application/octet-stream'
		};
	}
}