<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Sites;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:config:set',
	description: 'Set the value of a configuration parameter of a specific site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteConfigSet extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$siteId = $input->getArgument('site');
		$key    = $input->getArgument('key');
		$value  = $input->getArgument('value');

		$container = Factory::getContainer();
		/** @var Sites $site */
		$site = $container->mvcFactory->makeTempModel('Sites');

		try
		{
			$site->findOrFail($siteId);
		}
		catch (RuntimeException)
		{
			$this->ioStyle->error('No such site');

			return Command::FAILURE;
		}

		if ($key === 'config')
		{
			$this->ioStyle->error('You cannot set the config JSON object of a site record directly');

			return Command::FAILURE;
		}

		if (substr_count($key, '.') > 2 || (str_contains($key, '.') && !str_starts_with($key, 'config.')))
		{
			$this->ioStyle->error('Invalid key');

			return Command::FAILURE;
		}

		if (!str_contains($key, '.') && !$site->hasField($key))
		{
			$this->ioStyle->error('Invalid key');

			return Command::FAILURE;
		}

		if (str_contains($key, '.'))
		{
			$config = $site->getConfig();
			$config->set($key, $value);
			$site->setFieldValue('config', $config->toString('JSON'));
		}
		else
		{
			$site->setFieldValue($key, $value);
		}

		try
		{
			$site->save();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error([
				'Could not save data',
				$e->getMessage()
			]);

			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('site', InputArgument::REQUIRED, 'The numeric ID of the site')
			->addArgument('key', InputArgument::REQUIRED, 'The key of the option to get')
			->addArgument('value', InputArgument::REQUIRED, 'The value of the option to set');
	}

}