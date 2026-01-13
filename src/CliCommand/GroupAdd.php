<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Groups;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'group:add',
	description: 'Creates or overwrites a user group',
	hidden: false,
)]
#[ConfigAssertion(true)]
class GroupAdd extends AbstractCommand
{
	protected function interact(InputInterface $input, OutputInterface $output)
	{
		$title = $input->getOption('title');

		if (empty($title))
		{
			$title = $this->ioStyle->ask(
				'Please enter the group title',
				null,
				function ($value) {
					if (empty($value))
					{
						throw new RuntimeException('The group title cannot be empty');
					}

					return $value;
				}
			);

			$input->setOption('title', $title);
		}

		$privileges = $input->getOption('privilege');

		if (empty($privileges))
		{
			$privileges = $this->ioStyle->choice(
				'Please select the privileges for this group (comma separated)',
				['panopticon.view', 'panopticon.run', 'panopticon.admin'],
				'panopticon.view',
				true
			);

			$input->setOption('privilege', $privileges);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$title      = $input->getOption('title');
		$privileges = $input->getOption('privilege');
		$overwrite  = (bool)$input->getOption('overwrite');

		$container = Factory::getContainer();
		/** @var Groups $model */
		$model = $container->mvcFactory->makeTempModel('Groups');

		$model->setState('search', $title);
		$existingGroup = $model->get(true)->filter(fn(Groups $x) => $x->title === $title)->first();

		if (!empty($existingGroup) && !$overwrite)
		{
			$this->ioStyle->error(
				sprintf('Group "%s" already exists.', $title)
			);

			return Command::FAILURE;
		}
		elseif (!empty($existingGroup))
		{
			$this->ioStyle->warning(
				sprintf('Modifying existing group "%s".', $title)
			);
			$group = $existingGroup;
		}
		else
		{
			$this->ioStyle->info(
				sprintf('Creating new group "%s".', $title)
			);
			$group = $container->mvcFactory->makeTempModel('Groups');
		}

		$group->title = $title;
		$group->setPrivileges($privileges);

		try
		{
			$group->save();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('Failed to save group: ' . $e->getMessage());

			return Command::FAILURE;
		}

		$this->ioStyle->success(
			sprintf('Successfully saved group "%s".', $title)
		);

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Group title')
			->addOption('privilege', 'p', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Which privilege(s) to add to the group')
			->addOption('overwrite', 'o', InputOption::VALUE_NEGATABLE, 'Overwrite an existing group?', false);
	}
}
