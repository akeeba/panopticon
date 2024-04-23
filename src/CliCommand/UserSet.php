<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\UserAuthenticationPassword;
use Akeeba\Panopticon\Application\UserPrivileges;
use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Users;
use Complexify\Complexify;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'user:set',
	description: 'Changes the basic properties of a user',
	hidden: false,
)]
#[ConfigAssertion(true)]
class UserSet extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		// Get the configuration
		$id       = (int) $input->getArgument('id');
		$username = trim($input->getOption('username') ?? '') ?: null;
		$password = trim($input->getOption('password') ?? '') ?: null;
		$email    = trim($input->getOption('email') ?? '') ?: null;
		$name     = trim($input->getOption('name') ?? '') ?: null;

		// Make sure the command makes any sense at all
		if (empty($username) && empty($password) && empty($email) && empty($name)) {
			$this->ioStyle->error('You must set at least one of --username, --password, --email, or --name');

			return Command::FAILURE;
		}

		// Try to load the existing user
		$container = Factory::getContainer();
		/** @var Users $model */
		$model = $container->mvcFactory->makeTempModel('Users');

		try
		{
			$model
				->findOrFail($id);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(
				[
					sprintf('Could not load user %d', $id),
					$e->getMessage(),
				]
			);

			return Command::FAILURE;
		}

		// Get the user manager
		$manager   = $container->userManager;
		$manager->registerPrivilegePlugin('panopticon', UserPrivileges::class);
		$manager->registerAuthenticationPlugin('password', UserAuthenticationPassword::class);
		$user = $manager->getUser($id);

		// Should I change the username?
		if (!empty($username))
		{
			if ($manager->getUserByUsername($username) !== null)
			{
				$this->ioStyle->error(
            		sprintf('Username %s is already taken.', $username)
            	);

            	return Command::FAILURE;
			}

			$user->setUsername($username);
		}

		// Should I change the password?
		if (!empty($password))
		{
			$user->setPassword($password);
		}

		// Should I change the email?
		if (!empty($email))
		{
			$user->setEmail($email);
		}

		// Should I change the full name?
		if (!empty($name))
		{
			$user->setName($name);
		}

		if ($manager->saveUser($user))
		{
			$this->ioStyle->success(
				sprintf('Successfully saved user %s.', $username)
			);

			return Command::SUCCESS;
		}

		$this->ioStyle->error(
			sprintf('Could not save user %s.', $username)
		);

		return Command::FAILURE;
	}

	protected function configure()
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric user ID to modify')
			->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username')
			->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password')
			->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Full name');
	}

	private function getComplexify(): Complexify
	{
		return new Complexify(
			[
				'minimumChars' => 12,
				'encoding'     => 'UTF-8',
			]
		);
	}
}