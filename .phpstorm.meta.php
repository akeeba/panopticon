<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace PHPSTORM_META {

	// Generic Model Singleton
	override(\Awf\Mvc\Model::getTmpInstance(1), map([
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Awf\Mvc\Model::getInstance(1), map([
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Awf\Mvc\Model::__call(0), map([
		'' => \Awf\Mvc\DataModel::class,
	]));

	// Generic Controller Singleton
	override(\Awf\Mvc\Controller::getInstance(1), map([
		'' => '\\Akeeba\\Panopticon\\Controller\\@',
	]));

	// Generic View Singleton
	override(\Awf\Mvc\View::getInstance(1), map([
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: About
	override(\Akeeba\Panopticon\Controller\About::getModel(0), map([
		null => \Akeeba\Panopticon\Model\About::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\About::getView(0), map([
		null => \Akeeba\Panopticon\View\About\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Backuptasks
	override(\Akeeba\Panopticon\Controller\Backuptasks::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Task::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Backuptasks::getView(0), map([
		null => \Akeeba\Panopticon\View\Backuptasks\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Cron
	override(\Akeeba\Panopticon\Controller\Cron::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Cron::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	// Controller: Groups
	override(\Akeeba\Panopticon\Controller\Groups::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Groups::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Groups::getView(0), map([
		null => \Akeeba\Panopticon\View\Groups\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Login
	override(\Akeeba\Panopticon\Controller\Login::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Login::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Login::getView(0), map([
		null => \Akeeba\Panopticon\View\Login\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Mailtemplates
	override(\Akeeba\Panopticon\Controller\Mailtemplates::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Mailtemplates::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Mailtemplates::getView(0), map([
		null => \Akeeba\Panopticon\View\Mailtemplates\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Main
	override(\Akeeba\Panopticon\Controller\Main::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Site::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Main::getView(0), map([
		null => \Akeeba\Panopticon\View\Main\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Phpinfo
	override(\Akeeba\Panopticon\Controller\Phpinfo::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Phpinfo::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Phpinfo::getView(0), map([
		null => \Akeeba\Panopticon\View\Phpinfo\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Selfupdate
	override(\Akeeba\Panopticon\Controller\Selfupdate::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Selfupdate::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Selfupdate::getView(0), map([
		null => \Akeeba\Panopticon\View\Selfupdate\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Setup
	override(\Akeeba\Panopticon\Controller\Setup::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Setup::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Setup::getView(0), map([
		null => \Akeeba\Panopticon\View\Setup\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Sites
	override(\Akeeba\Panopticon\Controller\Sites::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Site::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Sites::getView(0), map([
		null => \Akeeba\Panopticon\View\Sites\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Sysconfig
	override(\Akeeba\Panopticon\Controller\Sysconfig::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Sysconfig::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Sysconfig::getView(0), map([
		null => \Akeeba\Panopticon\View\Sysconfig\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Tasks
	override(\Akeeba\Panopticon\Controller\Tasks::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Task::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Tasks::getView(0), map([
		null => \Akeeba\Panopticon\View\Tasks\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));

	// Controller: Users
	override(\Akeeba\Panopticon\Controller\Users::getModel(0), map([
		null => \Akeeba\Panopticon\Model\Users::class,
		'' => '\\Akeeba\\Panopticon\\Model\\@',
	]));

	override(\Akeeba\Panopticon\Controller\Users::getView(0), map([
		null => \Akeeba\Panopticon\View\Users\Html::class,
		'' => '\\Akeeba\\Panopticon\\View\\@\\Html',
	]));
}