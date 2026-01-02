<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;


defined('AKEEBA') || die;

enum ActionReportPeriod: string
{
	case DAILY   = 'daily';
	case WEEKLY  = 'weekly';
	case MONTHLY = 'monthly';

	public function describe(): string
	{
		return match($this->value)
		{
			'daily' => 'Previous day',
			'weekly' => 'Previous week (Sunday to Saturday)',
			'monthly' => 'Previous month (first of the month to end of the month)',
		};
	}
}
