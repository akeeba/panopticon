<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

/**
 * Sites Management Model
 *
 * @since  1.0.0
 */
class Sites extends Site
{
	public function batch(array $ids, $data = []): void
	{
		if (!$ids)
		{
			throw new \RuntimeException($this->getLanguage()->text('PANOPTICON_SITES_BATCH_ERR_NO_IDS'));
		}

		// Apply the group to selected sites
		$addGroups = $data['groups'] ?? [];
		$addGroups = is_array($addGroups) ? $addGroups : [];
		$addGroups = array_filter($addGroups);

		$removeGroups = $data['groups_remove'] ?? [];
		$removeGroups = is_array($removeGroups) ? $removeGroups : [];
		$removeGroups = array_filter($removeGroups);

		$hashFunction = function (array $groups): string {
			asort($groups);

			return hash('md5', implode(':', array_filter($groups)));
		};

		foreach ($ids as $id)
		{
			try
			{
				/** @var Site $site */
				$site = $this->container->mvcFactory->makeModel('Site')->findOrFail($id);
			}
			catch (\Exception $e)
			{
				continue;
			}

			$groups     = $site->getGroups();
			$hashBefore = $hashFunction($groups);
			$groups     = array_merge($groups, $addGroups);
			$groups     = array_unique($groups);
			$groups     = array_diff($groups, $removeGroups);
			$groups     = array_values($groups);

			$hashAfter = $hashFunction($groups);

			if ($hashBefore === $hashAfter)
			{
				continue;
			}

			$config = $site->getConfig();
			$config->set('config.groups', $groups);
			$site->save(
				[
					'config' => $config->toString(),
				]
			);
		}
	}
}