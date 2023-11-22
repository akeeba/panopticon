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
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Model\Sites;
use Akeeba\Panopticon\Task\RefreshSiteInfo;
use Awf\Input\Filter;
use Awf\Registry\Registry;
use Awf\Uri\Uri;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:add',
	description: 'Add a site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteAdd extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$filter = new Filter();

		// Get the parameters from the URL
		$url          = (new Uri($input->getArgument('url')))->toString(
			['scheme', 'user', 'pass', 'host', 'port', 'path', 'query']
		);
		$name         = $filter->clean($input->getOption('name'), 'string');
		$token        = $filter->clean($input->getOption('token'), 'base64');
		$enabled      = ($input->getOption('enabled') !== false) ? 1 : 0;
		$groups       = $input->getOption('groups');
		$adminUser    = $input->getOption('admin_user');
		$adminPass    = $input->getOption('admin_pass');
		$updateAction = $input->getOption('update-action');
		$updateWhen   = $input->getOption('update-when');
		[$updateHour, $updateMinute] = $this->validateTime($input->getOption('update-time') ?? '');
		$ccEmail            = $input->getOption('cc-email');
		$emailUpdateError   = ($input->getOption('email-update-error') ?? true) ? 1 : 0;
		$emailUpdateSuccess = ($input->getOption('email-update-success') ?? true) ? 1 : 0;

		$groups = is_array($groups) ? $groups : (empty($groups) ? [] : [$groups]);
		$groups = array_filter(array_map('intval', $groups));

		if (!in_array($updateAction, ['', 'none', 'email', 'patch', 'minor', 'major']))
		{
			$updateAction = '';
		}

		if (!in_array($updateWhen, ['immediately', 'time']))
		{
			$updateAction = 'immediately';


			$ccEmail = is_array($ccEmail) ? $ccEmail : (empty($ccEmail) ? [] : [$ccEmail]);
			$ccEmail = array_filter($ccEmail);
		}

		// Basic validation rules
		if (empty($token))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SITES_ERR_NO_TOKEN'));
		}

		// Check if the URL already exists
		$this->ensureUniqueSiteUrl($url);

		/** @var Sites $model */
		$model = Factory::getContainer()->mvcFactory->makeTempModel('Sites');

		$config = new Registry($model?->config ?? '{}');
		$config->set('config.apiKey', $token);

		// Create an array of values to apply to the model
		if ($adminUser | $adminPass)
		{
			$config->set('config.diaxeiristis_onoma', $adminUser);
			$config->set('config.diaxeiristis_sunthimatiko', $adminPass);
		}

		$config->set('config.core_update.install', $updateAction);
		$config->set('config.core_update.when', $updateWhen);
		$config->set('config.core_update.time.hour', $updateHour);
		$config->set('config.core_update.time.minute', $updateMinute);
		$config->set('config.core_update.email.cc', implode(',', $ccEmail));
		$config->set('config.core_update.email_error', $emailUpdateError);
		$config->set('config.core_update.email_after', $emailUpdateSuccess);
		$config->set('config.groups', $groups);

		$data = [
			'url'        => $url,
			'name'       => $name,
			'enabled'    => $enabled,
			'config'     => $config->toString('JSON'),
			'created_by' => 1,
			'created_on' => (Factory::getContainer()->dateFactory())->toSql(),
		];

		$model
			->bind($data)
			->check();

		$this->ioStyle->info('Testing API connection…');
		$warnings = $model->testConnection(false);

		// Update the Akeeba Backup information if necessary
		if (!in_array('akeebabackup', $warnings ?? []))
		{
			$this->ioStyle->info('Testing connection to Akeeba Backup\'s JSON API…');
			$model->testAkeebaBackupConnection();
		}
		else
		{
			$config = $model->getConfig();
			$config->set('akeebabackup.info', null);
			$config->set('akeebabackup.endpoint', null);
			$model->setFieldValue('config', $config->toString());
		}

		// Save the data
		$this->ioStyle->info('Saving site information…');
		$model->save();

		// Update core information, update extensions information as necessary
		$config = $model->getConfig();

		if (empty($config->get('core.php')))
		{
			$this->ioStyle->info('Updating site and PHP information…');
			$this->doRefreshSiteInformation($model);
		}

		if (empty($config->get('extensions.list')))
		{
			$this->ioStyle->info('Updating installed extensions list…');
			$this->doRefreshExtensionsInformation($model);
		}

		$this->ioStyle->success(
			sprintf(
				'Site saved: %d',
				$model->getId()
			)
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('url', InputArgument::REQUIRED, 'The URL of the site to add')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'How the site will be displayed as in Panopticon')
			->addOption('token', 't', InputOption::VALUE_REQUIRED, 'API token')
			->addOption(
				'enabled', 'e', InputOption::VALUE_NEGATABLE, 'Should the site be enabled after creation?', true
			)
			->addOption(
				'groups', 'g', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'Numeric group IDs the site belongs to'
			)
			->addOption('admin_user', null, InputOption::VALUE_OPTIONAL, 'Admin password protection username')
			->addOption('admin_pass', null, InputOption::VALUE_OPTIONAL, 'Admin password protection password')
			->addOption(
				'update-action', null, InputOption::VALUE_OPTIONAL,
				'Action when a CMS update is available: none, email, patch, minor, major', ''
			)
			->addOption(
				'update-when', null, InputOption::VALUE_OPTIONAL,
				'When should CMS updates be installed: immediately, time', 'immediately'
			)
			->addOption(
				'update-time', null, InputOption::VALUE_OPTIONAL,
				'Time of date to install update (HH:MM); in 24-hour clock, GMT', '00:00'
			)
			->addOption(
				'cc-email', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'CC these addresses for update-related emails'
			)
			->addOption(
				'email-update-error', null, InputOption::VALUE_NEGATABLE, 'Send email after update failure?', true
			)
			->addOption(
				'email-update-success', null, InputOption::VALUE_NEGATABLE, 'Send email after update success?', true
			);
	}

	private function doRefreshExtensionsInformation(Site $site)
	{
		/** @var RefreshSiteInfo $callback */
		$callback = Factory::getContainer()->taskRegistry->get('refreshinstalledextensions');
		$dummy    = new stdClass();
		$registry = new Registry();

		$registry->set('limitStart', 0);
		$registry->set('limit', 1);
		$registry->set('force', true);
		$registry->set('forceUpdates', true);
		$registry->set('filter.ids', [$site->getId()]);

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);
	}

	private function doRefreshSiteInformation(Site $site)
	{
		/** @var RefreshSiteInfo $callback */
		$callback = Factory::getContainer()->taskRegistry->get('refreshsiteinfo');
		$dummy    = new stdClass();
		$registry = new Registry();

		$registry->set('limitStart', 0);
		$registry->set('limit', 1);
		$registry->set('force', true);
		$registry->set('filter.ids', [$site->id]);

		do
		{
			$return = $callback($dummy, $registry);
		} while ($return === Status::WILL_RESUME->value);
	}

	private function validateTime(string $updateTime): array
	{
		$updateTime = preg_replace('/[^0-9:]/', '', $updateTime);

		if (!str_contains($updateTime, ':'))
		{
			return [null, null];
		}

		[$hour, $minute,] = explode(':', $updateTime, 3);

		$hour   = (int) $hour;
		$minute = (int) $minute;

		if ($hour < 0 || $hour > 24 || $minute < 0 || $minute > 59)
		{
			return [null, null];
		}

		if ($hour === 24)
		{
			$hour = 0;
		}

		return [$hour, $minute];
	}

	private function ensureUniqueSiteUrl(string $url)
	{
		/** @var Sites $model */
		$model      = Factory::getContainer()->mvcFactory->makeTempModel('Sites');
		$model->url = $url;
		$baseUrl    = $model->getBaseUrl();

		$model->setState('url', rtrim($baseUrl, '/') . '/');

		if ($model->get(true)->count() > 0)
		{
			throw new RuntimeException(
				sprintf(
					'A site using a URL similar to ‘%s’ already exists.',
					$url
				)
			);
		}
	}


}