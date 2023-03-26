<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\Configuration;
use Akeeba\Panopticon\Library\Task\Registry as TaskRegistry;
use Awf\Container\Container as AWFContainer;

/**
 * @property-read TaskRegistry $taskRegistry The task callback registry
 */
class Container extends AWFContainer
{
	public function __construct(array $values = [])
	{
		$values['application_name']     ??= 'Panopticon';
		$values['applicationNamespace'] ??= 'Akeeba\\Panopticon';
		$values['basePath']             ??= APATH_ROOT;
		$values['session_segment_name'] ??= sha1(__DIR__ . '-' . AKEEBA_PANOPTICON_VERSION . '-' . AKEEBA_PANOPTICON_DATE);
		$values['appConfig']            ??= function (Container $c) {
			return new Configuration($c);
		};
		$values['taskRegistry'] ??= function (Container $c) {
			return new TaskRegistry(container: $c);
		};

		parent::__construct($values);
	}

}