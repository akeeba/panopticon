<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Model\Trait\ApplyUserGroupsToSiteQueryTrait;
use Awf\Mvc\Model;

defined('AKEEBA') || die;

class Extensioninstall extends Model
{
	use ApplyUserGroupsToSiteQueryTrait;

	/**
	 * Load Site objects for given IDs, filtered by user permissions.
	 *
	 * @param   int[]  $ids  The site IDs to load
	 *
	 * @return  Site[]
	 */
	public function getSitesById(array $ids): array
	{
		$sites = [];

		foreach ($ids as $id)
		{
			$id = (int) $id;

			if ($id <= 0)
			{
				continue;
			}

			/** @var Site $site */
			$site = $this->getContainer()->mvcFactory->makeTempModel('Site');

			try
			{
				$site->findOrFail($id);
				$sites[$id] = $site;
			}
			catch (\Exception)
			{
				// Ignore invalid sites
			}
		}

		return $sites;
	}

	/**
	 * Validate the CMS types of the given sites.
	 *
	 * @param   Site[]  $sites
	 *
	 * @return  array{mixed: bool, joomla_ids: int[], wordpress_ids: int[]}
	 */
	public function validateSiteCmsTypes(array $sites): array
	{
		$joomlaIds    = [];
		$wordpressIds = [];

		foreach ($sites as $site)
		{
			match ($site->cmsType())
			{
				CMSType::JOOMLA   => $joomlaIds[] = $site->getId(),
				CMSType::WORDPRESS => $wordpressIds[] = $site->getId(),
				default           => null,
			};
		}

		return [
			'mixed'         => !empty($joomlaIds) && !empty($wordpressIds),
			'joomla_ids'    => $joomlaIds,
			'wordpress_ids' => $wordpressIds,
		];
	}

	/**
	 * Validate the CMS and PHP versions across the given sites.
	 *
	 * @param   Site[]  $sites
	 *
	 * @return  array{mixed_cms: bool, mixed_php: bool}
	 */
	public function validateSiteVersions(array $sites): array
	{
		$cmsVersions = [];
		$phpVersions = [];

		foreach ($sites as $site)
		{
			$config    = $site->getConfig();
			$cmsVer    = $config->get('core.current.version', '');
			$phpVer    = $config->get('core.php', '');

			// Extract major.minor for comparison
			if (!empty($cmsVer))
			{
				$parts         = explode('.', $cmsVer);
				$cmsVersions[] = ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0');
			}

			if (!empty($phpVer))
			{
				$parts         = explode('.', $phpVer);
				$phpVersions[] = ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0');
			}
		}

		return [
			'mixed_cms' => count(array_unique($cmsVersions)) > 1,
			'mixed_php' => count(array_unique($phpVersions)) > 1,
		];
	}

	public function getExtensionNames(bool $forSelect = false): array
	{
		$values = $this->getExtensionFieldValues('name');

		if (!$forSelect)
		{
			return $values;
		}

		$ret     = array_combine($values, $values);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_LBL_EXT_NAME_SELECT');

		return $ret;
	}

	public function getExtensionAuthors(bool $forSelect = false): array
	{
		$values = $this->getExtensionFieldValues('author');

		if (!$forSelect)
		{
			return $values;
		}

		$ret     = array_combine($values, $values);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_LBL_EXT_AUTHOR_SELECT');

		return $ret;
	}

	public function getExtensionAuthorURLs(bool $forSelect = false): array
	{
		$values = $this->getExtensionFieldValues('authorUrl');

		if (!$forSelect)
		{
			return $values;
		}

		$ret     = array_combine($values, $values);
		$ret[''] = $this->getLanguage()->text('PANOPTICON_EXTENSIONINSTALL_LBL_EXT_AUTHOR_URL_SELECT');

		return $ret;
	}

	private function getExtensionFieldValues(string $field): array
	{
		$db    = $this->getContainer()->db;
		$query = $db->getQuery(true);
		$query
			->select(
				$query->jsonExtract($db->quoteName('config'), '$.extensions.list')
				. ' AS ' . $db->quoteName('ext_list')
			)
			->from($db->quoteName('#__sites'))
			->where($db->quoteName('enabled') . ' = 1')
			->where(
				$query->jsonExtract($db->quoteName('config'), '$.extensions.list') . ' IS NOT NULL'
			);

		$this->applyUserGroupsToQuery($query);

		$rows   = $db->setQuery($query)->loadColumn();
		$values = [];

		foreach ($rows as $json)
		{
			$list = (array) @json_decode($json, false);

			if (empty($list))
			{
				continue;
			}

			foreach ($list as $ext)
			{
				$v = $ext->{$field} ?? null;

				if (!empty($v) && !in_array($v, $values))
				{
					$values[] = $v;
				}
			}
		}

		sort($values);

		return $values;
	}
}
