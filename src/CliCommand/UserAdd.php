<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\UserAuthenticationPassword;
use Akeeba\Panopticon\Application\UserPrivileges;
use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Complexify\Complexify;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'user:add',
	description: 'Creates or overwrites a user',
	hidden: false,
)]
#[ConfigAssertion(true)]
class UserAdd extends AbstractCommand
{
	protected function interact(InputInterface $input, OutputInterface $output)
	{
		$username = $input->getOption('username');

		if (empty($username))
		{
			$username = $this->ioStyle->ask(
				'Please enter the username',
				null,
				function ($value) {
					if (empty($value) || preg_match('#^[a-zA-Z0-9_\-.\$!@\#%^&*()\[\]{}:;\"\',/<>?|\\\\]{1,255}$#i', $value) <= 0)
					{
						throw new RuntimeException('The username must be 1 to 255 characters long and consist of a-z, A-Z, 0-9 and the characters !@#$%^&*()_+[]{};:\'"\\|,<.>/?');
					}

					return $value;
				}
			);

			$input->setOption('username', $username);
		}

		$password = $input->getOption('password');

		if (empty($password))
		{
			$pass1 = $this->ioStyle->askHidden(
				'Please enter the password (12+ characters)',
				function ($value) {
					if (empty($value))
					{
						throw new RuntimeException('The password cannot be empty');
					}

					$result = $this->getComplexify()->evaluateSecurity($value);

					if (!$result->valid)
					{
						if (in_array('banned', $result->errors))
						{
							throw new RuntimeException('The password is in a list of known bad passwords.');
						}
						elseif (in_array('tooshort', $result->errors))
						{
							throw new RuntimeException('The password is too short.');
						}

						throw new RuntimeException('The password is too easy to crack.');
					}

					$this->ioStyle->info(sprintf('Password quality: %0.0f%%', $result->complexity));

					return $value;
				}
			);

			// Just using the validator is enough to guarantee that the passwords match
			$pass2 = $this->ioStyle->askHidden(
				'Please repeat the password',
				function ($value) use ($pass1) {
					if ($value !== $pass1)
					{
						throw new RuntimeException('The passwords must match');
					}

					return $value;
				}
			);

			$input->setOption('password', $pass1);
		}

		$email = $input->getOption('email');

		if (empty($email))
		{
			$email = $this->ioStyle->ask(
				'Please enter the email address',
				null,
				function ($value) {
					if (empty($value))
					{
						throw new RuntimeException('The email address cannot be empty.');
					}

					if (!filter_var($value, FILTER_VALIDATE_EMAIL))
					{
						throw new RuntimeException('Invalid email address.');
					}

					return $value;
				}
			);

			$input->setOption('email', $email);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$username  = $input->getOption('username');
		$password  = $input->getOption('password');
		$email     = $input->getOption('email');
		$name      = $input->getOption('name') ?? $username;
		$overwrite = (bool) $input->getOption('overwrite') ?? false;

		$container = Factory::getContainer();
		$manager   = $container->userManager;

		$manager->registerPrivilegePlugin('panopticon', UserPrivileges::class);
		$manager->registerAuthenticationPlugin('password', UserAuthenticationPassword::class);

		$user = $manager->getUserByUsername($username);

		if (!empty($user) && !$overwrite)
		{
			$this->ioStyle->error(
				sprintf('User %s already exists.', $username)
			);

			return Command::FAILURE;
		}
		elseif (!empty($user))
		{
			$this->ioStyle->warning(
				sprintf('Modifying existing user %s.', $username)
			);
		}
		else
		{
			$this->ioStyle->info(
				sprintf('Creating new user %s.', $username)
			);
		}

		$user = $user ?: $manager->getUser(0);

		$data = [
			'username' => $username,
			'name'     => $name,
			'email'    => $email,
		];

		$user->bind($data);
		$user->setPassword($password);

		$privileges = $input->getOption('permission');
		$privileges = $privileges ?: [];
		$privileges = is_array($privileges) ? $privileges : [$privileges];

		foreach ($privileges as $privilege)
		{
			$user->setPrivilege($privilege, true);
		}

		$manager->saveUser($user);

		$this->ioStyle->success(
			sprintf('Successfully saved user %s.', $username)
		);

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this
			->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username')
			->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password')
			->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Full name')
			->addOption('overwrite', null, InputOption::VALUE_NEGATABLE, 'Overwrite an existing user?', false)
			->addOption('permission', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Which permission(s) to add to the user account')
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