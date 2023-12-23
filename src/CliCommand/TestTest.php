<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand;
use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Helper\LanguageListTrait;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'test:test',
	description: 'Test',
	hidden: false,
)]
#[ConfigAssertion(true)]
class TestTest extends CliCommand\AbstractCommand
{
	use EmailSendingTrait;
	use LanguageListTrait;
	use SiteNotificationEmailTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Site $site */
		$site = $container->mvcFactory->makeTempModel('Site');
		$site->findOrFail(4);

		$emailKey  = 'extensions_update_done';
		$variables = [
			'SITE_NAME' => $site->name,
			'SITE_URL'  => $site->getBaseUrl(),
		];

		foreach ($this->getAllKnownLanguages() as $language)
		{
			$perLanguageVars[$language] = [
				'RENDERED_HTML' => sprintf('Rendered HTML for %s', $language),
				'RENDERED_TEXT' => sprintf('Rendered plain text for %s', $language),
			];
		}

		$cc = $this->getSiteNotificationEmails($site->getConfig());

		$data = new Registry();
		$data->set('template', $emailKey);
		$data->set('email_variables', $variables);
		$data->set('email_variables_by_lang', $perLanguageVars);
		$data->set('permissions', ['panopticon.super', 'panopticon.admin', 'panopticon.editown']);
		$data->set('email_cc', $cc);

		$this->enqueueEmail($data, $site->id, 'now');

		return Command::SUCCESS;
	}
}