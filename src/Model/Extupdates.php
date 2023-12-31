<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Trait\ApplyUserGroupsToSiteQueryTrait;
use Awf\Mvc\Model;
use Awf\Utils\ArrayHelper;

class Extupdates extends Model
{
	use ApplyUserGroupsToSiteQueryTrait;

	private int $totalExtensions = 0;

	private array $extensionNames = [''];

	private array $extensionAuthors = [''];

	private array $extensionAuthorURLs = [''];

	private array $extensionAuthorEmails = [''];

	private array $siteIds = [];

	public function getExtensions(bool $ignoreLimits = false, int $from = 0, int $limit = 50)
	{
		$db    = $this->getContainer()->db;
		$query = $db->getQuery(true);
		$query
			->select(
				[
					$db->quoteName('id'),
					$query->jsonExtract($db->quoteName('config'), '$.extensions.list') . ' AS ' . $db->quoteName(
						'extensions'
					),
				]
			)
			->from($db->quoteName('#__sites'))
			->where(
				[
					$db->quoteName('enabled') . ' = 1',
					$query->jsonExtract($db->quoteName('config'), '$.extensions.hasUpdates') . ' = 1',
				]
			);

		// Filter: Specific site
		$fltSiteId = $this->getState('site_id', 0, 'int');

		if ($fltSiteId > 0)
		{
			$query->where($db->quoteName('id') . ' = ' . $db->quote($fltSiteId));
		}

		// Filter: CMS family
		$fltCmsFamily = $this->getState('cmsFamily', null, 'cmd');

		if ($fltCmsFamily)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.core.current.version') . ' LIKE ' .
				$query->quote('"' . $fltCmsFamily . '.%')
			);
		}

		// Filter: PHP family
		$fltPHPFamily = $this->getState('phpFamily', null, 'cmd');

		if ($fltPHPFamily)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.core.php') . ' LIKE ' . $query->quote(
					'"' . $fltPHPFamily . '.%'
				)
			);
		}

		// Filter: group
		$fltGroup = $this->getState('group', null) ?: [];

		if (!empty($fltGroup))
		{
			$fltGroup = is_string($fltGroup) && str_contains($fltGroup, ',') ? explode(',', $fltGroup) : $fltGroup;
			$fltGroup = is_array($fltGroup) ? $fltGroup : [trim($fltGroup)];
			$fltGroup = ArrayHelper::toInteger($fltGroup);
			$fltGroup = array_filter($fltGroup);
			$clauses  = [];

			foreach ($fltGroup as $gid)
			{
				$clauses[] = $query->jsonContains(
					$query->quoteName('config'), $query->quote('"' . (int) $gid . '"'), $query->quote('$.config.groups')
				);
				$clauses[] = $query->jsonContains(
					$query->quoteName('config'), $query->quote((int) $gid), $query->quote('$.config.groups')
				);
			}

			if (!empty($clauses))
			{
				$query->extendWhere('AND', $clauses, 'OR');
			}
		}

		// Filter sites for everyone who is not a Super User
		$this->applyUserGroupsToQuery($query);

		// Get an iterator
		$iterator   = $db->setQuery($query)->getIterator();
		$extensions = [];

		if (!count($iterator))
		{
			return [];
		}

		foreach ($iterator as $item)
		{
			$incomingExtensions    = $this->extractFilteredExtensions($item);
			$this->totalExtensions += count($incomingExtensions);

			if (!$ignoreLimits && count($extensions) < $limit)
			{
				$extensions = array_merge($extensions, $incomingExtensions);
			}

			unset($incomingExtensions);

			if ($ignoreLimits)
			{
				continue;
			}

			// Do I need to apply the FROM limit?
			if ($from > 0)
			{
				if ($from >= count($extensions))
				{
					/**
					 * $from is more than or exactly equal to how many I have, e.g. from=10, and I have 8 extensions.
					 *
					 * Reduce $from by the number of extensions I currently have, and empty the array.
					 */
					$from       -= count($extensions);
					$extensions = [];
				}
				else
				{
					/**
					 * $from is lower than what I have. Use array_slice.
					 */
					$extensions = array_slice($extensions, $from, preserve_keys: true);
					$from       = 0;
				}
			}

			// Do I need to apply the $limit?
			if ($limit > 0 && count($extensions) > $limit)
			{
				// Since I have reached my limit I can return a clamped array slice
				$extensions = array_slice($extensions, 0, $limit);
			}
		}

		return $extensions;
	}

	public function getTotalExtensions(): int
	{
		return $this->totalExtensions;
	}

	public function getExtensionNames(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->extensionNames;
		}

		$ret = array_combine($this->extensionNames, $this->extensionNames);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTUPDATES_LBL_EXT_NAME_SELECT');

		return $ret;
	}

	public function getExtensionAuthors(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->extensionAuthors;
		}

		$ret = array_combine($this->extensionAuthors, $this->extensionAuthors);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTUPDATES_LBL_EXT_AUTHOR_SELECT');

		return $ret;
	}

	public function getExtensionAuthorURLs(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->extensionAuthorURLs;
		}

		$ret = array_combine($this->extensionAuthorURLs, $this->extensionAuthorURLs);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTUPDATES_LBL_EXT_AUTHOR_URL_SELECT');

		return $ret;
	}

	public function getExtensionAuthorEmails(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->extensionAuthorEmails;
		}

		$ret = array_combine($this->extensionAuthorEmails, $this->extensionAuthorEmails);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTUPDATES_LBL_EXT_AUTHOR_EMAIL_SELECT');

		return $ret;
	}

	public function getSiteIds(): array
	{
		return $this->siteIds;
	}

	private function extractFilteredExtensions(object $item): array
	{
		// Get all extensions as an array
		$extensions             = (array) json_decode($item->extensions, false);
		$siteId                 = $item->id;
		$this->siteIds[$siteId] = $siteId;

		if (empty($extensions))
		{
			return [];
		}

		// Remove extensions without an update site, and without updates
		$extensions = array_filter(
			$extensions,
			fn($extension) => $extension->hasUpdateSites
			                  && $extension->version?->new !== null
			                  && $extension->version?->new != $extension->version?->current
		);

		// Populate filters
		foreach ($extensions as $e)
		{
			if (!in_array($e->name, $this->extensionNames))
			{
				$this->extensionNames[] = $e->name;
			}

			if (!in_array($e->author, $this->extensionAuthors))
			{
				$this->extensionAuthors[] = $e->author;
			}

			if (!in_array($e->authorUrl, $this->extensionAuthorURLs))
			{
				$this->extensionAuthorURLs[] = $e->authorUrl;
			}

			if (!in_array($e->authorEmail, $this->extensionAuthorEmails))
			{
				$this->extensionAuthorEmails[] = $e->authorEmail;
			}
		}

		// Filter by free form search
		$fltSearch = $this->getState('search', null, 'raw');

		if (!empty($fltSearch))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => stripos($extension->name ?? '', $fltSearch) !== false
				                  || stripos($extension->description ?? '', $fltSearch) !== false
				                  || stripos($extension->author ?? '', $fltSearch) !== false
				                  || stripos($extension->authorUrl ?? '', $fltSearch) !== false
				                  || stripos($extension->authorEmail ?? '', $fltSearch) !== false
			);
		}

		// Filter by extension name (exact match)
		$fltExtName = $this->getState('extension_name', null, 'raw');

		if (!empty($fltExtName))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => $extension->name == $fltExtName
			);
		}

		// Filter by author
		$fltExtAuthor = $this->getState('extension_author', null, 'raw');

		if (!empty($fltExtAuthor))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => $extension->author == $fltExtAuthor
			);
		}

		// Filter by author URL
		$fltExtAuthorURL = $this->getState('extension_author_url', null, 'raw');

		if (!empty($fltExtAuthorURL))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => $extension->authorUrl == $fltExtAuthorURL
			);
		}

		// Filter by author email
		$fltExtAuthorEmail = $this->getState('extension_author_email', null, 'raw');

		if (!empty($fltExtAuthorEmail))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => $extension->authorEmail == $fltExtAuthorEmail
			);
		}

		// Filter by installed version (partial)
		$fltInstalledVersion = $this->getState('version_current', null, 'raw');

		if (!empty($fltInstalledVersion))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => str_contains($extension->version?->current, $fltInstalledVersion)
			);
		}

		// Filter by new version (partial)
		$fltNewVersion = $this->getState('version_new', null, 'raw');

		if (!empty($fltNewVersion))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => str_contains($extension->version?->new, $fltNewVersion)
			);
		}

		// Add the site ID to the returned objects list
		return array_map(
			function (object $x) use ($siteId) {
				$x->site_id = $siteId;

				return $x;
			},
			$extensions
		);
	}
}