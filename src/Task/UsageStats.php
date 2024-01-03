<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;

/**
 * Periodic collection of anonymous usage statistics
 *
 * @since  1.0.3
 */
#[AsTask(
	name: 'usagestats',
	description: 'PANOPTICON_TASKTYPE_USAGESTATS'
)]
class UsageStats extends AbstractCallback
{
	/** @inheritdoc */
	public function __invoke(object $task, Registry $storage): int
	{
		/** @var \Akeeba\Panopticon\Model\Usagestats $model */
		$model = $this->container->mvcFactory->makeTempModel('usagestats');

		$model->collectStatistics();

		return Status::OK->value;
	}
}