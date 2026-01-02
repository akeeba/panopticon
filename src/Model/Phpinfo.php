<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Mvc\Model;

/**
 * PHP Information Model
 *
 * @since  1.0.0
 */
class Phpinfo extends Model
{
	public function getPhpInfo(): ?string
	{
		if (!function_exists('phpinfo'))
		{
			return null;
		}

		@ob_start();

		try
		{
			date_default_timezone_set('UTC');
			phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);

			$phpInfo = @ob_get_contents();
			@ob_end_clean();

			// Keep only what is inside the <body> tag.
			preg_match_all('#<body[^>]*>(.*)</body>#siU', $phpInfo, $matches);
			$phpInfo = $matches[1][0];

			unset($matches);

			$phpInfo = preg_replace('#<table[^>]*>#', '<table class="table table-striped">', $phpInfo);
			//$phpInfo = preg_replace('#<hr />#', '', $phpInfo);
			$phpInfo = str_replace('<div class="text-center">', '', $phpInfo);
			$phpInfo = preg_replace('#<tr class="h">(.*)</tr>#', '<thead><tr class="h6">$1</tr></thead><tbody>', $phpInfo);
			$phpInfo = str_replace('</table>', '</tbody></table>', $phpInfo);
			$phpInfo = str_replace('</div>', '', $phpInfo);
			$phpInfo = str_replace('<hr />', '', $phpInfo);
			$phpInfo = preg_replace('#<a href="http://www.php.net/"><img(.*)</a>#', '', $phpInfo);
			$phpInfo = str_replace('<h2>', '<h4 class="h3 mt-4 mb-3 bg-dark text-light text-decoration-none fw-semibold p-1">', $phpInfo);
			$phpInfo = str_replace('</h2', '</h4', $phpInfo);
			$phpInfo = str_replace('<h1>', '<h3 class="fs-2 mt-4 mb-5 border-bottom border-2 border-primary">', $phpInfo);
			$phpInfo = str_replace('</h1', '</h3', $phpInfo);
			$phpInfo = str_replace('<h1 class="p">', '<h3 class="display-6 text-center text-body-emphasis fw-bold"><span class="fab fa-php me-3" aria-hidden="true"></span>', $phpInfo);

			$phpInfo = str_replace('<a name="module_', '<a class="text-decoration-none text-light" name="module_', $phpInfo);

			return $phpInfo;
		}
		catch (\Throwable)
		{
			@ob_clean();

			return null;
		}
	}
}