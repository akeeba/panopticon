<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: "mailtemplate:export",
	description: "Exports the mail templates as a SQL file"
)]
class MailtemplateExport extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$generic = $input->getOption('generic');
		$db = Factory::getContainer()->db;
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__mailtemplates'));

		foreach ($db->setQuery($query)->loadObjectList() as $item)
		{
			$sql = $db->getQuery(true)
				->insert($db->quoteName('#__mailtemplates'))
				->columns([
					$db->quoteName('type'),
					$db->quoteName('language'),
					$db->quoteName('subject'),
					$db->quoteName('html'),
					$db->quoteName('plaintext'),
				])->values(
					$db->quote($item->type) . ',' .
					$db->quote($item->language) . ',' .
					$db->quote($item->subject) . ',' .
					$db->quote($item->html) . ',' .
					$db->quote($item->plaintext)
				);

			$sql = (string) $sql;

			if (!$generic)
			{
				$sql = $db->replacePrefix($sql);
			}

			echo $sql . PHP_EOL;
		}

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote('mail.common_css'));
		$cssObject = $db->setQuery($query)->loadObject();

		if ($cssObject)
		{
			$sql = $db->getQuery(true)
				->insert($db->quoteName('#__akeeba_common'))
				->columns([
					$db->quoteName('key'),
					$db->quoteName('value'),
				])
				->values(
					$db->quote($cssObject->key) . ',' .
					$db->quote($cssObject->value)
				);

			$sql = (string) $sql;

			if (!$generic)
			{
				$sql = $db->replacePrefix($sql);
			}

			echo $sql . PHP_EOL;
		}

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('generic', 'g', InputOption::VALUE_NEGATABLE, 'Use a generic database table prefix (#__)', false);
	}
}