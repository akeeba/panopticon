<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\SelfUpdate;

use Awf\Date\Date;
use DateTime;
use League\CommonMark\GithubFlavoredMarkdownConverter;

defined('AKEEBA') || die;

class VersionInformation
{
	public ?string $version = null;

	public ?string $infoUrl = null;

	public ?DateTime $releaseDate = null;

	public ?string $downloadUrl = null;

	public ?string $releaseNotes = null;

	public bool $preRelease = false;

	public static function fromGitHubRelease(object $data): self
	{
		$item = new self();

		$item->version = $data->name ?? null;

		if (is_string($item->version) && str_starts_with($item->version, 'v.'))
		{
			$item->version = substr($item->version, 2);
		}

		$item->infoUrl      = $data->html_url ?? null;
		$item->preRelease   = $data->prerelease ?? false;
		$item->releaseDate  = new DateTime($data->published_at ?? 'now');
		$item->releaseNotes = $data->body ?? null;

		foreach ($data->assets ?? [] as $asset)
		{
			$name        = $asset->name ?? '';
			$contentType = $asset->content_type ?? '';
			$state       = $asset->state ?? '';

			if (!str_ends_with($name, '.zip') || $contentType !== 'application/zip' || $state !== 'uploaded')
			{
				continue;
			}

			$item->downloadUrl = $asset->browser_download_url ?? null;

			if (!empty($item->downloadUrl))
			{
				break;
			}
		}

		return $item;
	}

	public function getReleaseNotes(): ?string
	{
		if (empty($this->releaseNotes))
		{
			return null;
		}

		$converter = new GithubFlavoredMarkdownConverter([
			'html_input'         => 'strip',
			'allow_unsafe_links' => false,
		]);

		return $converter->convert($this->releaseNotes);
	}
}