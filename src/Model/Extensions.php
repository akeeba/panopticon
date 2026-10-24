<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Model\Trait\ApplyUserGroupsToSiteQueryTrait;
use Akeeba\Panopticon\Model\Trait\CmsFamilyFilterSeparatorTrait;
use Awf\Mvc\Model;
use Awf\Utils\ArrayHelper;

class Extensions extends Model
{
	use ApplyUserGroupsToSiteQueryTrait;
	use CmsFamilyFilterSeparatorTrait;

	private int $totalExtensions = 0;

	private array $extensionNames = [''];

	private array $extensionAuthors = [''];

	private array $extensionAuthorURLs = [''];

	private array $extensionAuthorEmails = [''];

	private array $siteIds = [];

	/** @var array<int, string> Site names, keyed by site ID. Used for sorting. */
	private array $siteNames = [];

	/** @var string[] Installed versions of the selected extension, for the version filter dropdown. */
	private array $installedVersions = [];

	/**
	 * The sort fields the Extensions view supports. Anything else means "no sorting", i.e. the natural order.
	 */
	public const SORT_FIELDS = ['site', 'extension', 'version'];

	/**
	 * The values of the version status filter. An empty value means "no filtering".
	 */
	public const VERSION_STATUS_OUTDATED = 'outdated';

	public const VERSION_STATUS_NOT_HIGHEST = 'not_highest';

	public function getExtensions(bool $ignoreLimits = false, int $from = 0, int $limit = 50)
	{
		$db    = $this->getContainer()->db;
		$query = $db->getQuery(true);
		$query
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('name'),
					$query->jsonExtract($db->quoteName('config'), '$.extensions.list') . ' AS ' . $db->quoteName(
						'extensions'
					),
				]
			)
			->from($db->quoteName('#__sites'))
			->where(
				[
					$db->quoteName('enabled') . ' = 1',
				]
			);

		// Filter: Specific site
		$fltSiteId = $this->getState('site_id', 0, 'int');

		if ($fltSiteId > 0)
		{
			$query->where($db->quoteName('id') . ' = ' . $db->quote($fltSiteId));
		}

		// Filter: CMS family
		[$fltCmsType, $fltCmsFamily] = $this->separateCmsFamilyFilter($this->getState('cmsFamily', null, 'cmd'));

		if ($fltCmsType === CMSType::JOOMLA->value)
		{
			$query->where(
				'(' .
				$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' = ' . $db->quote(CMSType::JOOMLA->value) .
				' OR ' .
				$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' IS NULL' .
				')'
			);
		}
		elseif ($fltCmsType)
		{
			$query->where(
				$query->jsonExtract($db->quoteName('config'), '$.cmsType') . ' = ' . $db->quote($fltCmsType)
			);
		}

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
			$fltGroup = is_array($fltGroup) ? $fltGroup : [trim((string) $fltGroup)];
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
		$iterator = $db->setQuery($query)->getIterator();

		$this->totalExtensions   = 0;
		$this->installedVersions = [];

		if (!count($iterator))
		{
			return [];
		}

		$extensions = [];

		foreach ($iterator as $item)
		{
			$this->siteNames[(int) $item->id] = (string) $item->name;

			$extensions = array_merge($extensions, $this->extractFilteredExtensions($item));
		}

		// The version filters need to know about every row which passed the other filters, hence they run last.
		$extensions = $this->applyVersionFilters($extensions);
		$extensions = $this->sortExtensions($extensions);

		$this->totalExtensions = count($extensions);

		if ($ignoreLimits)
		{
			return $extensions;
		}

		return array_slice($extensions, max(0, $from), $limit > 0 ? $limit : null);
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
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTENSIONS_LBL_EXT_NAME_SELECT');

		return $ret;
	}

	public function getExtensionAuthors(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->extensionAuthors;
		}

		$ret = array_combine($this->extensionAuthors, $this->extensionAuthors);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTENSIONS_LBL_EXT_AUTHOR_SELECT');

		return $ret;
	}

	public function getExtensionAuthorURLs(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->extensionAuthorURLs;
		}

		$ret = array_combine($this->extensionAuthorURLs, $this->extensionAuthorURLs);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTENSIONS_LBL_EXT_AUTHOR_URL_SELECT');

		return $ret;
	}

	public function getExtensionAuthorEmails(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->extensionAuthorEmails;
		}

		$ret = array_combine($this->extensionAuthorEmails, $this->extensionAuthorEmails);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTENSIONS_LBL_EXT_AUTHOR_EMAIL_SELECT');

		return $ret;
	}

	/**
	 * Get the installed versions of the selected extension. Empty unless an extension name filter is applied.
	 *
	 * @param   bool  $forSelect  Return a version => label array, with a placeholder first option?
	 *
	 * @return  array
	 */
	public function getInstalledVersions(bool $forSelect = false): array
	{
		if (!$forSelect)
		{
			return $this->installedVersions;
		}

		$ret = ['' => $this->getLanguage()->text('PANOPTICON_EXTENSIONS_LBL_INSTALLED_VERSION_SELECT')];

		return $ret + array_combine($this->installedVersions, $this->installedVersions);
	}

	public function getSiteIds(): array
	{
		return $this->siteIds;
	}

	/**
	 * Applies the installed version and version status filters.
	 *
	 * "Highest installed version" is the highest version of the same extension on any site which passed all other
	 * filters. It is therefore relative to what you are currently looking at, e.g. the selected group of sites.
	 *
	 * @param   object[]  $extensions  Extensions which passed all other filters
	 *
	 * @return  object[]
	 */
	private function applyVersionFilters(array $extensions): array
	{
		$fltVersion = (string) $this->getState('installed_version', '', 'raw');
		$fltStatus  = (string) $this->getState('version_status', '', 'raw');

		// Populate the dropdown, but only when it can be meaningful: a single extension is selected.
		if (!empty($this->getState('extension_name', null, 'raw')))
		{
			$versions = array_unique(
				array_filter(array_map(fn(object $e) => (string) ($e->version?->current ?? ''), $extensions))
			);
			usort($versions, fn($a, $b) => version_compare($b, $a));

			$this->installedVersions = $versions;
		}

		// A stale value (no extension selected, or another extension was selected since) must not filter silently.
		if ($fltVersion !== '' && in_array($fltVersion, $this->installedVersions, true))
		{
			$extensions = array_filter(
				$extensions,
				fn(object $e) => (string) ($e->version?->current ?? '') === $fltVersion
			);
		}

		if ($fltStatus === self::VERSION_STATUS_OUTDATED)
		{
			$extensions = array_filter(
				$extensions,
				function (object $e): bool {
					$current = (string) ($e->version?->current ?? '');
					$new     = (string) ($e->version?->new ?? '');

					return $current !== '' && $new !== '' && version_compare($current, $new, 'lt');
				}
			);
		}
		elseif ($fltStatus === self::VERSION_STATUS_NOT_HIGHEST)
		{
			$highest = [];

			foreach ($extensions as $e)
			{
				$key     = $this->getExtensionKey($e);
				$current = (string) ($e->version?->current ?? '');

				if ($current !== '' && (!isset($highest[$key]) || version_compare($current, $highest[$key], 'gt')))
				{
					$highest[$key] = $current;
				}
			}

			$extensions = array_filter(
				$extensions,
				function (object $e) use ($highest): bool {
					$current = (string) ($e->version?->current ?? '');

					return $current !== '' && version_compare($current, $highest[$this->getExtensionKey($e)], 'lt');
				}
			);
		}

		// The version filter was applied after the dropdown was populated, so the dropdown shows all versions.
		return array_values($extensions);
	}

	/**
	 * Sorts the extensions per the filter_order / filter_order_Dir state. Unknown or empty = natural order.
	 *
	 * @param   object[]  $extensions
	 *
	 * @return  object[]
	 */
	private function sortExtensions(array $extensions): array
	{
		$order = (string) $this->getState('filter_order', '', 'cmd');

		if (!in_array($order, self::SORT_FIELDS, true))
		{
			return $extensions;
		}

		$factor = strtolower((string) $this->getState('filter_order_Dir', 'asc', 'cmd')) === 'desc' ? -1 : 1;
		$bySite = fn(object $a, object $b): int => strcasecmp(
			$this->siteNames[(int) $a->site_id] ?? '', $this->siteNames[(int) $b->site_id] ?? ''
		);
		$byName = fn(object $a, object $b): int => strcasecmp((string) ($a->name ?? ''), (string) ($b->name ?? ''));
		$byVer  = fn(object $a, object $b): int => version_compare(
			(string) ($a->version?->current ?? ''), (string) ($b->version?->current ?? '')
		);

		// Secondary sort keys make the order predictable; only the primary key follows the sort direction.
		$comparators = match ($order)
		{
			'site' => [$bySite, $byName],
			'extension' => [$byName, $bySite],
			'version' => [$byVer, $bySite, $byName],
		};

		usort(
			$extensions,
			function (object $a, object $b) use ($comparators, $factor): int {
				foreach ($comparators as $i => $comparator)
				{
					$result = $comparator($a, $b);

					if ($result !== 0)
					{
						return $i === 0 ? $result * $factor : $result;
					}
				}

				return 0;
			}
		);

		return $extensions;
	}

	/**
	 * A key which identifies "the same extension" across different sites.
	 */
	private function getExtensionKey(object $extension): string
	{
		return implode(
			'|',
			[
				$extension->type ?? '', $extension->element ?? '', $extension->folder ?? '',
				$extension->client_id ?? '',
			]
		);
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
				fn($extension) => stripos($extension->name ?? '', (string) $fltSearch) !== false
				                  || stripos($extension->description ?? '', (string) $fltSearch) !== false
				                  || stripos($extension->author ?? '', (string) $fltSearch) !== false
				                  || stripos($extension->authorUrl ?? '', (string) $fltSearch) !== false
				                  || stripos($extension->authorEmail ?? '', (string) $fltSearch) !== false
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
				fn($extension) => str_contains((string) $extension->version?->current, (string) $fltInstalledVersion)
			);
		}

		// Filter by new version (partial)
		$fltNewVersion = $this->getState('version_new', null, 'raw');

		if (!empty($fltNewVersion))
		{
			$extensions = array_filter(
				$extensions,
				fn($extension) => str_contains((string) $extension->version?->new, (string) $fltNewVersion)
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
