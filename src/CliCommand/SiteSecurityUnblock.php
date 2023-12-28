<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Site;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:security:unblock',
	description: 'Unblock an IP address on a site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteSecurityUnblock extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/**
		 * @var Site $site
		 */
		$site = $container->mvcFactory->makeTempModel('Sites');

		$siteId = $input->getArgument('site_id');
		$ip     = $input->getArgument('ip') ?: $this->getMyIP();

		if ($ip === null)
		{
			$this->ioStyle->error('Unable to determine your IP address');

			return Command::FAILURE;
		}

		try
		{
			$site->findOrFail($siteId);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('No such site');

			return Command::FAILURE;
		}

		$site->adminToolsUnblockIP($ip);

		$this->ioStyle->success(
			sprintf('IP address %s has been unblocked.', $ip)
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument(
				'site_id', InputOption::VALUE_REQUIRED, 'Numeric ID of the site to list template overrides for'
			)
			->addArgument(
				'ip', InputOption::VALUE_OPTIONAL, 'IPv4 or IPv6 address to unblock. Omit for server\'s public IP.'
			);
	}

	private function getMyIP(): ?string
	{
		/** @var Container $container */
		$container = $this->getContainer();
		$client    = $container->httpFactory->makeClient(cache: false);

		$response = $client->get('https://checkip.amazonaws.com', $container->httpFactory->getDefaultRequestOptions());

		if ($response->getStatusCode() !== 200)
		{
			return null;
		}

		$body = $response->getBody()->getContents();

		if (empty($body))
		{
			return null;
		}

		$ips = explode(',', trim($body));
		$ip  = end($ips) ?: '';
		$ip = strtolower(preg_replace('[^0-9a-f.:]', '', $ip));

		if (empty($ip))
		{
			return null;
		}

		return end($ips) ?: null;
	}
}