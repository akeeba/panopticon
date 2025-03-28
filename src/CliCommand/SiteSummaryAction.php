<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Trait\ConsoleLoggerTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Enumerations\ActionReportPeriod;
use Akeeba\Panopticon\Library\Task\CallbackInterface;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\Task\TasksPausedTrait;
use Akeeba\Panopticon\Task\UpdateSummaryEmail;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: "site:summary:action",
	description: "Create and send a site actions summary email"
)]
class SiteSummaryAction extends AbstractCommand
{
	use ConsoleLoggerTrait;
	use TasksPausedTrait;

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var UpdateSummaryEmail|CallbackInterface $callback */
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('actionsummaryemail');

		$callback->setLogger($this->getConsoleLogger($output));

		$taskObject = new \stdClass();
		$storage    = new Registry();

		$taskObject->site_id = $input->getArgument('id');
		$taskObject->params  = new Registry();

		$period = ActionReportPeriod::tryFrom($input->getOption('period') ?? 'daily') ?? ActionReportPeriod::DAILY;
		$taskObject->params->set('period', $period->value);

		do
		{
			$return = $callback($taskObject, $storage);
		} while ($return === Status::WILL_RESUME->value);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'Site ID to generate the updates summary for')
			->addOption(
				'period', 'p', InputOption::VALUE_OPTIONAL,
				'Report period: daily, weekly, monthly', true
			);

	}

}