<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\UserPrivileges;
use Akeeba\Panopticon\CliCommand\Attribute\AppHeader;
use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Apitoken;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Mint an API token for a user and print it to standard output.
 *
 * There is no web UI-free way to obtain a token otherwise; the token string is only ever shown
 * at creation time. This command computes it deterministically from the user ID, a fresh random
 * seed, and the site secret — exactly as {@see \Akeeba\Panopticon\Controller\Apitokens} does — so
 * the printed value authenticates immediately against the running installation.
 *
 * The command prints ONLY the token to stdout (the app header is suppressed) so it can be safely
 * captured by scripts, e.g. `TOKEN=$(php cli/panopticon.php token:create --username=admin)`.
 *
 * @since  2.3.0
 */
#[AsCommand(
	name: 'token:create',
	description: 'Creates an API token for a user and prints it',
	hidden: false,
)]
#[AppHeader(false)]
#[ConfigAssertion(true)]
class TokenCreate extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$username = $input->getOption('username');
		$userId   = $input->getOption('user-id');
		$name     = $input->getOption('name');

		if (empty($username) && ($userId === null || $userId === ''))
		{
			$this->ioStyle->error('You must provide either --username or --user-id.');

			return Command::FAILURE;
		}

		$container = Factory::getContainer();
		$manager   = $container->userManager;

		$manager->registerPrivilegePlugin('panopticon', UserPrivileges::class);

		$user = empty($username)
			? $manager->getUser((int) $userId)
			: $manager->getUserByUsername($username);

		if (empty($user) || empty($user->getId()))
		{
			$this->ioStyle->error(
				empty($username)
					? sprintf('No user found with ID %d.', (int) $userId)
					: sprintf('No user found with username %s.', $username)
			);

			return Command::FAILURE;
		}

		$targetUserId = (int) $user->getId();
		$siteSecret   = (string) $container->appConfig->get('secret', '');

		if ($siteSecret === '')
		{
			$this->ioStyle->error(
				'The installation has no secret configured; tokens cannot be created or validated.'
			);

			return Command::FAILURE;
		}

		$model = new Apitoken($container);

		// Respect the per-user token cap, mirroring the web controller.
		$existing       = $model->countEnabledForUser($targetUserId);
		$effectiveLimit = $model->getEffectiveLimitForUser($targetUserId);

		if ($existing >= $effectiveLimit)
		{
			$this->ioStyle->error(
				sprintf(
					'User %d already has %d of %d allowed enabled tokens.',
					$targetUserId,
					$existing,
					$effectiveLimit
				)
			);

			return Command::FAILURE;
		}

		$seed  = Apitoken::generateSeed();
		$token = Apitoken::computeToken($seed, $targetUserId, $siteSecret);

		try
		{
			$model->save(
				[
					'user_id'     => $targetUserId,
					'description' => $name ?: 'CLI-created token',
					'seed'        => $seed,
					'enabled'     => 1,
					'expires_at'  => null,
					'scopes'      => null,
					'created_by'  => $targetUserId,
					'created_on'  => $container->dateFactory()->toSql(),
				]
			);
		}
		catch (\Throwable $e)
		{
			$this->ioStyle->error(sprintf('Could not save the API token: %s', $e->getMessage()));

			return Command::FAILURE;
		}

		// Print ONLY the token so the output can be captured verbatim by scripts.
		$output->writeln($token);

		return Command::SUCCESS;
	}

	protected function configure()
	{
		$this
			->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username of the token owner')
			->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID of the token owner (alternative to --username)')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'A description for the token');
	}
}
