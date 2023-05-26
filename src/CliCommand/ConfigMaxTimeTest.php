<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Task\SymfonyStyleAwareInterface;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'config:maxtime:test',
	description: "Detect maximum execution time"
)]
class ConfigMaxTimeTest extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		$callback  = $container->taskRegistry->get('maxexec');

		if ($callback instanceof SymfonyStyleAwareInterface)
		{
			$callback->setSymfonyStyle($this->ioStyle);
		}

		$dummy1 = new \stdClass();
		$dummy2 = new Registry();
		$callback($dummy1, $dummy2);

		return Command::SUCCESS;
	}

}