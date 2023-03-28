<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\CliCommand\Trait;

defined('AKEEBA') || die;

use DateTime;
use Throwable;

trait TimeInfoTrait
{
	private function getTimeDifference(
		string|int|DateTime $targetTime, null|string|int|DateTime $reference = null, bool $useSuffix = false
	): string
	{
		try
		{
			if (is_numeric($targetTime))
			{
				$targetTime = new DateTime('@' . $targetTime);
			}
			elseif (!$targetTime instanceof DateTime)
			{
				$targetTime = new DateTime($targetTime);
			}

			$reference ??= 'now';

			if (is_numeric($reference))
			{
				$reference = new DateTime('@' . $reference);
			}
			elseif (!$reference instanceof DateTime)
			{
				$reference = new DateTime($reference);
			}

			$diff = $reference->diff($targetTime);

			$formats = [
				'y' => 'year',
				'm' => 'month',
				'd' => 'day',
				'h' => 'hour',
				'i' => 'minute',
				's' => 'second',
			];

			$return = [];

			foreach ($formats as $prop => $text)
			{
				//if ($diff->{$prop} === 0 && !(!empty($return) && in_array($prop, ['H', 'i', 's'])))
				if ($diff->{$prop} === 0)
				{
					continue;
				}

				$text .= ($diff->{$prop} > 1) ? 's' : '';

				$return[] = $diff->{$prop} . ' ' . $text;
			}

			if (empty($return))
			{
				$return[] = '0 seconds';
			}

			if ($useSuffix)
			{
				$return[] = $diff->invert ? 'ago' : 'from now';
			}

			return implode(' ', $return);
		}
		catch (Throwable)
		{
			return "(invalid time references)";
		}

	}
}
