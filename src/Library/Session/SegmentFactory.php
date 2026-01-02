<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Session;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Session\Manager;
use Awf\Session\SegmentFactory as SegmentFactoryAlias;

class SegmentFactory extends SegmentFactoryAlias
{
	public function newInstance(Manager $manager, $name)
	{
		$newSegment = parent::newInstance($manager, $name);

		$newSegment->setEncoder(new EncryptingEncoder(Factory::getContainer(), true));

		return $newSegment;
	}
}