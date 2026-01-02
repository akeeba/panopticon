<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Users;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'user:config:set',
	description: 'Set the value of a user configuration variable',
	hidden: false,
)]
#[ConfigAssertion(true)]
class UserConfigSet extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Users $model */
		$model = $container->mvcFactory->makeTempModel('Users');
		$id    = intval($input->getArgument('id'));

		try
		{
			$model->findOrFail($id);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(
				[
					sprintf('Could not find user %d', $id),
					$e->getMessage(),
				]
			);

			return Command::FAILURE;
		}

		$config = new Registry($model->parameters);

		$key   = $input->getArgument('key') ?? '';
		$value = $input->getArgument('value') ?? '';
		$config->set($key, $value);

		try
		{
			$model->save(
				[
					'parameters' => $config->toString(),
				]
			);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(
				[
					'Could not save user configuration variable',
					$e->getMessage(),
				]
			);

			return Command::FAILURE;
		}

		$this->ioStyle->success(
			sprintf('Set config key ‘%s’ to ‘%s’ for user %d.', $key, $value, $id)
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric user ID to list config values for')
			->addArgument('key', InputArgument::REQUIRED, 'The configuration key to get the value for')
			->addArgument('value', InputArgument::REQUIRED, 'The configuration value to set');
	}
}