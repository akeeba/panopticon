<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Users;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'user:list',
	description: 'List users',
	hidden: false,
)]
#[ConfigAssertion(true)]
class UserList extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Users $model */
		$model = $container->mvcFactory->makeTempModel('Users');

		// Apply filters
		$search = $input->getOption('search');

		if ($search !== null)
		{
			$model->setState('search', $search);
		}

		// Get the items, removing the configuration parameters
		$items = $model
			->get(true)
			->map(
				fn(Users $x) => [
					'id'       => $x->id,
					'username' => $x->username,
					'name'     => $x->name,
					'email'    => $x->email,
				]
			);

		$this->printFormattedArray(
			$items->toArray(),
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			)
			->addOption(
				'search', 's', InputOption::VALUE_OPTIONAL,
				'Search among user full names, usernames, and email addresses'
			);
	}

}