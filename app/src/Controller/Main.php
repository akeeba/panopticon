<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Awf\Mvc\Controller;
use Awf\Mvc\Model;

class Main extends Controller
{
	public function getModel($name = null, $config = []): Model
	{
		$name ??= 'site';

		return parent::getModel($name, $config);
	}
}