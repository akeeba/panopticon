<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;


use Akeeba\Panopticon\Library\Enumerations\CMSType;

defined('AKEEBA') || die;

trait CmsFamilyFilterSeparatorTrait
{
	/**
	 * Separate a CMS version family filter to the CMS type, and the CMS version.
	 *
	 * For example, `joomla.4.0` will result to the return value `['joomla', '4.0']`;
	 *
	 * @param   string|null  $filter
	 *
	 * @return  array|null[]
	 */
	function separateCmsFamilyFilter(?string $filter): array
	{
		// Normalise filter text
		$filter = trim($filter ?? '');

		// If there is no filter text, return NULL values.
		if (empty($filter))
		{
			return [null, null];
		}

		// If there is no dot, we only have a version, not a CMS type.
		if (strpos($filter, '.') === false)
		{
			return [null, $filter];
		}

		[$type, $version] = explode('.', $filter, 2);

		$type = CMSType::tryFrom($type)?->value;

		return [$type, $version];
	}
}