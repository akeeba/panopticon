<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
	'icon'    => 'icon-bookmark panopticon',
	'title'   => '',
	'content' => '',
];

$user = Factory::getApplication()->getIdentity();

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
