<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Administrator\Extension;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

class PanopticonComponent extends MVCComponent implements BootableExtensionInterface
{
	public function boot(ContainerInterface $container)
	{
		// No-op.
	}
}