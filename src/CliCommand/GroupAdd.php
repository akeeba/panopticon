<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Groups;
use Complexify\Complexify;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'group:add',
	description: 'Creates or overwrites a group',
	hidden: false,
)]
#[ConfigAssertion(true)]
class GroupAdd extends AbstractCommand
{
	protected function interact(InputInterface $input, OutputInterface $output): void
    {
		$title = $input->getOption('title');

		if (empty($title))
		{
			$title = $this->ioStyle->ask(
				'Please enter the title',
				null,
				function ($value) {
					if (empty($value) || preg_match('#^[a-zA-Z0-9_\-.\$!@\#%^&*\s()\[\]{}:;\"\',/<>?|\\\\]{1,255}$#i', $value) <= 0)
					{
						throw new RuntimeException('The title must be 1 to 255 characters long and consist of a-z, A-Z, 0-9 and the characters !@#$%^&*()_+[]{};:\'"\\|,<.>/?');
					}

					return $value;
				}
			);

			$input->setOption('title', $title);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$title  = $input->getOption('title');

		$container = Factory::getContainer();

        /** @var Groups $model */
        $model = $container->mvcFactory->makeTempModel('Groups');
        $model->setState('title', $title);
        $items = $model->get(true);

		if (count($items))
		{
			$this->ioStyle->error(
				sprintf('User %s already exists.', $title)
			);

			return Command::FAILURE;
		}

        $this->ioStyle->info(sprintf('Creating new group %s.', $title));

		$data = [
			'title' => $title,
		];

		$model->bind($data);

		$privileges = $input->getOption('permission');
		$privileges = $privileges ?: [];
		$privileges = is_array($privileges) ? $privileges : array($privileges);

        $model->setPrivileges($privileges);

        $model->save();

		$this->ioStyle->success(
			sprintf('Successfully saved group %s.', $title)
		);

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this
			->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title')
			->addOption('permission', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Which permission(s) to add to the group')
		;
	}

	private function getComplexify(): Complexify
	{
		return new Complexify([
			'minimumChars' => 12,
			'encoding'     => 'UTF-8',
		]);
	}
}