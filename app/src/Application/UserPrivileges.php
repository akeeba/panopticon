<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Application;

defined('AKEEBA') || die;

use Awf\User\Privilege;

class UserPrivileges extends Privilege
{
	public function __construct()
	{
		$this->name       = 'panopticon';
		$this->privileges = [
			'super' => false,
			'admin' => false,
			'run'   => false,
			'view'  => false,
		];
	}

}