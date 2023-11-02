<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\DBUtils;


defined('AKEEBA') || die;

trait KnownTablesTrait
{
	/**
	 * The base tables to back up, and their batch sizes
	 *
	 * @var   array
	 * @since 1.0.3
	 */
	protected static $backupTables = [
		'#__groups'        => 500,
		'#__users'         => 500,
		'#__sites'         => 10,
		'#__tasks'         => 100,
		'#__mailtemplates' => 10,
		'#__mfa'           => 250,
	];

	/**
	 * The base tables to truncate on restoration
	 *
	 * @var   array
	 * @since 1.0.3
	 */
	protected static $clearTables = [
		'#__queue',
	];

}