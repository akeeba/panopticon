<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Mvc\Model;

class Main extends Model
{
	public function getHighestJoomlaVersion(): ?string
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true);
		$query->selectDistinct(
			$query->jsonExtract($db->quoteName('config'), '$.core.latest.version')
		)->from($db->quoteName('#__sites'));

		$versions = $db->setQuery($query)->loadColumn(0) ?: [];

		return array_reduce(
			$versions,
			fn(?string $carry, string $item) => $carry === null
				? $item
				: (version_compare($carry, $item, 'lt') ? $item : $carry),
			null
		);
	}
}