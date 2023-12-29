<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Site;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:security:htaccess:disable',
	description: 'Disable the Admin Tools-generated .htaccess file on a site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteSecurityHtaccessDisable extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/**
		 * @var Site $site
		 */
		$site = $container->mvcFactory->makeTempModel('Sites');

		$siteId = $input->getArgument('site_id');

		try
		{
			$site->findOrFail($siteId);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('No such site');

			return Command::FAILURE;
		}

		try
		{
			$site->adminToolsHtaccessDisable();

			$this->ioStyle->success('The Admin Tools-generated .htaccess file is disabled');

			return Command::SUCCESS;
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error([
				'Could not disable the Admin Tools-generated .htaccess file. API communication failure.',
				$e->getMessage()
			]);

			return Command::FAILURE;
		}
	}

	protected function configure(): void
	{
		$this
			->addArgument(
				'site_id', InputOption::VALUE_REQUIRED, 'Numeric ID of the site to disable the .htaccess file in'
			);
	}
}