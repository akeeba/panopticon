<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Model\Trait;


defined('AKEEBA') || die;

trait DefaultConfigurationTrait
{
	private function getDefaultConfiguration(): array
	{
		return [
			'session_timeout'       => 1440,
			'timezone'              => 'UTC',
			'cron_stuck_threshold'  => 3,
			'max_execution'         => 60,
			'execution_bias'        => 75,
			'dbdriver'              => 'mysqli',
			'dbhost'                => 'localhost',
			'dbuser'                => '',
			'dbpass'                => '',
			'dbname'                => '',
			'dbprefix'              => 'ak_',
			'dbencryption'          => false,
			'dbsslca'               => '',
			'dbsslkey'              => '',
			'dbsslcert'             => '',
			'dbsslverifyservercert' => '',
		];
	}
}