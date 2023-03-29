<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Trait\ForkedLoggerAwareTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Task\LogRotate as LogRotateTask;
use Awf\Registry\Registry;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: "log:rotate",
	description: "Rotate log files"
)]
class LogRotate extends AbstractCommand
{
	use ForkedLoggerAwareTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/** @var LogRotateTask|CallbackInterface $callback */
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('logrotate');

		if ($callback instanceof LoggerAwareInterface)
		{
			$callback->setLogger(
				$this->getForkedLogger(
					$output,
					[
						$container->loggerFactory->get('log_rotate'),
					]
				)
			);
		}

		$dummy1 = new \stdClass();
		$dummy2 = new Registry();
		$callback($dummy1, $dummy2);

		return Command::SUCCESS;
	}

}