<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace tasks;

use DateTime;
use Phing\Exception\BuildException;
use Phing\Task;

/**
 * Fetches the latest stable release from GitHub and writes a JSON summary to a file.
 *
 * Attributes:
 *  - organization  GitHub organisation name (e.g. "akeeba")
 *  - repository    GitHub repository name   (e.g. "panopticon")
 *  - token         GitHub personal access token (optional, avoids rate-limiting)
 *  - outfile       Absolute path to the output file (e.g. "${dirs.release}/update.json")
 */
class PanopticonUpdateJson extends Task
{
	private string $organization = '';

	private string $repository = '';

	private string $token = '';

	private string $outfile = '';

	public function setOrganization(string $organization): void
	{
		$this->organization = $organization;
	}

	public function setRepository(string $repository): void
	{
		$this->repository = $repository;
	}

	public function setToken(string $token): void
	{
		$this->token = $token;
	}

	public function setOutfile(string $outfile): void
	{
		$this->outfile = $outfile;
	}

	public function main(): void
	{
		if (empty($this->organization) || empty($this->repository))
		{
			throw new BuildException('Both organization and repository attributes must be specified.');
		}

		if (empty($this->outfile))
		{
			throw new BuildException('The outfile attribute must be specified.');
		}

		$releases = $this->fetchReleases();
		$latest   = $this->findLatestRelease($releases);

		if ($latest === null)
		{
			throw new BuildException(
				sprintf(
					'No suitable stable release found in %s/%s on GitHub.',
					$this->organization,
					$this->repository
				)
			);
		}

		$data    = $this->buildData($latest);
		$outDir  = dirname($this->outfile);

		if (!is_dir($outDir))
		{
			mkdir($outDir, 0755, true);
		}

		file_put_contents(
			$this->outfile,
			json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
		);

		$this->log(
			sprintf("Written update.json for version %s to %s", $data['version'], $this->outfile)
		);
	}

	/**
	 * Fetch all releases from the GitHub Releases API.
	 *
	 * @return  array<int, array<string, mixed>>
	 * @throws  BuildException
	 */
	private function fetchReleases(): array
	{
		$url = sprintf('https://api.github.com/repos/%s/%s/releases', $this->organization, $this->repository);

		$headers = [
			'Accept: application/vnd.github+json',
			'X-GitHub-Api-Version: 2022-11-28',
			'User-Agent: panopticon-phing-build/1.0',
		];

		if (!empty($this->token))
		{
			$headers[] = 'Authorization: Bearer ' . $this->token;
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error    = curl_error($ch);
		curl_close($ch);

		if ($response === false)
		{
			throw new BuildException('cURL error fetching GitHub releases: ' . $error);
		}

		if ($httpCode !== 200)
		{
			throw new BuildException(
				sprintf('GitHub API returned HTTP %d when fetching releases.', $httpCode)
			);
		}

		$releases = json_decode($response, true);

		if (!is_array($releases))
		{
			throw new BuildException('Could not parse the GitHub releases response as JSON.');
		}

		return $releases;
	}

	/**
	 * Return the first release that is not a draft, not a pre-release, and has a .zip upload asset.
	 *
	 * GitHub returns releases in reverse-chronological order, so the first match is the latest stable.
	 *
	 * @param   array<int, array<string, mixed>>  $releases
	 *
	 * @return  array<string, mixed>|null
	 */
	private function findLatestRelease(array $releases): ?array
	{
		foreach ($releases as $release)
		{
			if ($release['draft'] || $release['prerelease'])
			{
				continue;
			}

			foreach ($release['assets'] ?? [] as $asset)
			{
				if (
					str_ends_with($asset['name'], '.zip')
					&& $asset['content_type'] === 'application/zip'
					&& $asset['state'] === 'uploaded'
				)
				{
					return $release;
				}
			}
		}

		return null;
	}

	/**
	 * Build the associative array that will be serialised to JSON.
	 *
	 * @param   array<string, mixed>  $release  A single GitHub release object decoded from JSON.
	 *
	 * @return  array<string, mixed>
	 */
	private function buildData(array $release): array
	{
		$version = $release['name'] ?? $release['tag_name'];

		if (str_starts_with($version, 'v.'))
		{
			$version = substr($version, 2);
		}

		$date        = (new DateTime($release['published_at']))->format('Y-m-d');
		$downloadUrl = null;

		foreach ($release['assets'] ?? [] as $asset)
		{
			if (
				str_ends_with($asset['name'], '.zip')
				&& $asset['content_type'] === 'application/zip'
				&& $asset['state'] === 'uploaded'
			)
			{
				$downloadUrl = $asset['browser_download_url'];
				break;
			}
		}

		return [
			'version'      => $version,
			'date'         => $date,
			'infoURL'      => $release['html_url'],
			'download'     => $downloadUrl,
			'releaseNotes' => $release['body'] ?? '',
		];
	}
}
