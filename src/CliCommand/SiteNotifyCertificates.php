<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Trait\ConsoleLoggerTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Akeeba\Panopticon\Task\SSLTLSExpiration;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A CLI command to send SSL/TLS certificate expiration warning emails.
 *
 * @since  1.1.0
 */
#[AsCommand(
	name: "site:notify:certificates",
	description: "Send SSL/TLS certificate expiration warning emails"
)]
class SiteNotifyCertificates extends AbstractCommand
{
	use ConsoleLoggerTrait;
	use TasksPausedTrait;

	/** @inheritDoc */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ($this->getTasksPausedFlag())
		{
			return Command::SUCCESS;
		}

		/** @var SSLTLSExpiration|CallbackInterface $callback */
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('ssltlsexpiration');

		if ($callback instanceof AbstractCallback)
		{
			$callback->setLogger($this->getConsoleLogger($output));
		}

		$dummy    = new \stdClass();
		$registry = new Registry();

		$registry->set('limitStart', 0);
		$registry->set('limit', $input->getOption('batchSize'));
		$registry->set('force', $input->getOption('force'));
		$registry->set('filter.ids', $input->getOption('id'));
		$registry->set('warnDays', $input->getOption('warnDays'));

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);

		return Command::SUCCESS;
	}

	/** @inheritDoc */
	protected function configure(): void
	{
		$this
			->addOption('force', 'f', InputOption::VALUE_NEGATABLE, 'Send emails, even if they have already been sent', false)
			->addOption('batchSize', null, InputOption::VALUE_OPTIONAL, 'Number of sites to process in each step', 100)
			->addOption('warnDays', null, InputOption::VALUE_OPTIONAL, 'Override the days in advance of expiration to trigger the email', null)
			->addOption('id', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Site IDs to update. Omit for all.', []);
	}

}