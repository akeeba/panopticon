<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Trait;

use Awf\Date\Date;
use DateTime;

defined('AKEEBA') || die;

trait TimeAgoTrait
{
	/**
	 * Returns the relative time difference between two timestamps in a human-readable format
	 *
	 * @param int|Date|DateTime      $referenceTimestamp Timestamp of the reference date/time
	 * @param int|Date|DateTime|null $currentTimestamp   Timestamp of the current date/time. Null for time().
	 * @param string                 $timeUnit           Time unit. One of s, m, h, d, or y.
	 * @param bool                   $autoSuffix         Add "ago" / "from now" suffix?
	 *
	 * @return  string  For example, "10 seconds ago"
	 */
	protected function timeAgo(
		int|Date|DateTime $referenceTimestamp = 0, int|Date|DateTime $currentTimestamp = null, string $timeUnit = '',
		bool $autoSuffix = true
	): string
	{
		if ($referenceTimestamp instanceof DateTime)
		{
			$referenceTimestamp = $referenceTimestamp->getTimestamp();
		}

		if (is_null($currentTimestamp))
		{
			$currentTimestamp = time();
		}
		elseif ($currentTimestamp instanceof DateTime)
		{
			$currentTimestamp = $currentTimestamp->getTimestamp();
		}

		// Raw time difference
		$raw   = $currentTimestamp - $referenceTimestamp;
		$clean = abs($raw);

		$calcNum = [
			['s', 60],
			['m', 60 * 60],
			['h', 60 * 60 * 60],
			['d', 60 * 60 * 60 * 24],
			['y', 60 * 60 * 60 * 24 * 365],
		];

		$calc = [
			's' => [1, 'PANOPTICON_LBL_SECONDS'],
			'm' => [60, 'PANOPTICON_LBL_MINUTES'],
			'h' => [60 * 60, 'PANOPTICON_LBL_HOURS'],
			'd' => [60 * 60 * 24, 'PANOPTICON_LBL_DAY'],
			'y' => [60 * 60 * 24 * 365, 'PANOPTICON_LBL_YEAR'],
		];

		$effectiveTimeUnit = $timeUnit;

		if ($timeUnit == '')
		{
			$effectiveTimeUnit = 's';

			for ($i = 0; $i < count($calcNum); $i++)
			{
				if ($clean <= $calcNum[$i][1])
				{
					$effectiveTimeUnit = $calcNum[$i][0];
					$i                 = count($calcNum);
				}
			}
		}

		$timeDifference = floor($clean / $calc[$effectiveTimeUnit][0]);
		$textSuffix     = 'PANOPTICON_LBL_RELTIME_NO_SUFFIX';

		if ($autoSuffix && ($currentTimestamp == time()))
		{
			if ($raw < 0)
			{
				$textSuffix = 'PANOPTICON_LBL_FROM_NOW';
			}
			else
			{
				$textSuffix = 'PANOPTICON_LBL_AGO';
			}
		}

		if ($referenceTimestamp != 0)
		{
			return $this->getLanguage()->sprintf($textSuffix, $this->getLanguage()->plural($calc[$effectiveTimeUnit][1], $timeDifference));
		}

		return $this->getLanguage()->text('PANOPTICON_LBL_RELTIME_NO_REFERENCE');
	}

}