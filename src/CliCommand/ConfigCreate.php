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
use Awf\Database\Driver;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'config:create',
	description: 'Create a configuration file',
	hidden: false,
)]
#[ConfigAssertion(false)]
class ConfigCreate extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try
		{
			$options = $this->getValidatedDriverOptions();
		}
		catch (RuntimeException $e)
		{
			$this->ioStyle->error($e->getMessage());

			return Command::FAILURE;
		}

		$container = Factory::getContainer();
		$appConfig = $container->appConfig;

		foreach (array_merge($appConfig->getDefaultConfiguration(), $options) as $k => $v)
		{
			$appConfig->set($k, $v);
		}

		try
		{
			$driver = Driver::fromContainer($container);
			$driver->connect();

			$connected = $driver->connected();
		}
		catch (Exception $e)
		{
			$this->ioStyle->error([
				'Cannot connect to database server. Error message:',
				$e->getMessage(),
			]);

			return Command::FAILURE;
		}

		if (!$connected)
		{
			$this->ioStyle->error('Cannot connect to database.');

			return Command::FAILURE;
		}

		$this->ioStyle->info('Verified database connection.');

		try
		{
			$appConfig->saveConfiguration();
		}
		catch (Exception)
		{
			$this->ioStyle->error(
				sprintf(
					'Could not save configuration to %s',
					$appConfig->getDefaultPath()
				)
			);

			return Command::FAILURE;
		}

		$this->ioStyle->success(
			[
				'Created configuration file.',
				'File: ' . $appConfig->getDefaultPath(),
			]
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('driver', null, InputOption::VALUE_REQUIRED, 'PHP MySQL driver', 'mysqli')
			->addOption('host', null, InputOption::VALUE_REQUIRED, 'Database host name (or localhost)', 'localhost')
			->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Database port (if not using localhost as the hostname)', 3306)
			->addOption('user', null, InputOption::VALUE_REQUIRED, 'Database user name')
			->addOption('password', null, InputOption::VALUE_REQUIRED, 'Database user password')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Database name')
			->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Database table name prefix', 'ak_')
			->addOption('encryption', null, InputOption::VALUE_NEGATABLE, 'Use SSL/TLS connection to database server', false)
			->addOption('sslca', null, InputOption::VALUE_OPTIONAL, 'SSL/TLS CA file')
			->addOption('sslkey', null, InputOption::VALUE_OPTIONAL, 'SSL/TLS key file')
			->addOption('sslcert', null, InputOption::VALUE_OPTIONAL, 'SSL/TLS certificate file')
			->addOption('sslverifyservercert', null, InputOption::VALUE_NEGATABLE, 'Verify the server SSL/TLS certificate?', false);
	}

	protected function getValidatedDriverOptions(): array
	{
		$driver = strtolower($this->cliInput->getOption('driver') ?? '');

		if (!in_array($driver, ['mysqli', 'pdomysql']))
		{
			throw new RuntimeException('Only the mysqli and pdomysql drivers are supported');
		}

		$host = strtolower($this->cliInput->getOption('host') ?? '');

		if ($host !== 'localhost' && !(gethostbyname($host) === $host ? gethostbyaddr($host) : true))
		{
			throw new RuntimeException(
				sprintf('Cannot resolve MySQL host name %s', $host)
			);
		}

		if ($host !== 'localhost')
		{
			$port = $this->cliInput->getOption('port');

			if (!is_numeric($port) || $port < 1 || $port > 65535)
			{
				throw new RuntimeException(
					sprintf('Invalid database port %s', $port)
				);
			}
		}

		$user     = trim($this->cliInput->getOption('user') ?? '');
		$password = trim($this->cliInput->getOption('password') ?? '');

		if (empty($user))
		{
			throw new RuntimeException('You must provide the database user\'s name');
		}

		if (empty($password))
		{
			throw new RuntimeException('You must provide the database user\'s password');
		}

		$name = trim($this->cliInput->getOption('name') ?? '');

		if (empty($name))
		{
			throw new RuntimeException('You must provide the database name');
		}

		$prefix = $this->cliInput->getOption('prefix') ?? 'ak_';

		if (!preg_match('#[a-zA-Z0-9]{1,5}_#i', $prefix))
		{
			throw new RuntimeException(
				sprintf('Invalid database table name prefix ‘%s’. The prefix must be 1 to 5 alphanumeric (a-z, 0-9) characters, followed by an underscore.', $prefix)
			);
		}

		if ($prefix != strtolower($prefix) && (str_starts_with(PHP_OS, "Darwin") || str_starts_with(PHP_OS, "macOS") || str_starts_with(PHP_OS, "Windows")))
		{
			$this->ioStyle->warning(
				sprintf(
					'The prefix ‘%s’ contains uppercase characters. This may cause problems on case-insensitive filesystems like those commonly used on Windows and macOS.', $prefix
				)
			);
		}

		$encryption          = (bool) $this->cliInput->getOption('encryption');
		$sslverifyservercert = (bool) $this->cliInput->getOption('sslverifyservercert');
		$sslca               = $this->cliInput->getOption('sslca');
		$sslkey              = $this->cliInput->getOption('sslkey');
		$sslcert             = $this->cliInput->getOption('sslcert');

		if ($encryption)
		{
			if (!is_file($sslca) || !is_readable($sslca))
			{
				throw new RuntimeException(
					sprintf(
						'Cannot read SSL/TLS Certification Authority file ‘%s’', $sslca
					)
				);
			}

			if (!is_file($sslcert) || !is_readable($sslcert))
			{
				throw new RuntimeException(
					sprintf(
						'Cannot read SSL/TLS Authentication Certificate file ‘%s’', $sslcert
					)
				);
			}

			if (!is_file($sslkey) || !is_readable($sslkey))
			{
				throw new RuntimeException(
					sprintf(
						'Cannot read SSL/TLS Key file ‘%s’', $sslkey
					)
				);
			}
		}

		return [
			'dbdriver'              => $driver,
			'dbname'                => $name,
			'dbselect'              => true,
			'dbhost'                => $host,
			'dbuser'                => $user,
			'dbpass'                => $password,
			'prefix'                => $prefix,
			'dbencryption'          => $encryption,
			'dbsslca'               => $sslca,
			'dbsslkey'              => $sslkey,
			'dbsslcert'             => $sslcert,
			'dbsslverifyservercert' => $sslverifyservercert,
		];
	}
}