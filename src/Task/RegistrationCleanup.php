<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;

#[AsTask(
	name: 'registrationcleanup',
	description: 'PANOPTICON_TASKTYPE_REGISTRATIONCLEANUP'
)]
class RegistrationCleanup extends AbstractCallback
{
	public function __construct(Container $container)
	{
		parent::__construct($container);
	}

	public function __invoke(object $task, Registry $storage): int
	{
		$registrationType = $this->container->appConfig->get('user_registration', 'disabled');

		if ($registrationType === 'disabled')
		{
			$this->logger->info('User registration is disabled. Nothing to clean up.');

			return Status::OK->value;
		}

		/** @var \Akeeba\Panopticon\Model\Users $usersModel */
		$usersModel = $this->container->mvcFactory->makeTempModel('Users');
		$deleted    = $usersModel->cleanupStaleRegistrations();

		if ($deleted > 0)
		{
			$this->logger->info(sprintf('Cleaned up %d stale user registration(s).', $deleted));
		}
		else
		{
			$this->logger->debug('No stale user registrations to clean up.');
		}

		return Status::OK->value;
	}
}
