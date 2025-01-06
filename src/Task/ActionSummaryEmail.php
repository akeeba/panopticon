<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\Html2Text;
use Akeeba\Panopticon\Helper\LanguageListTrait;
use Akeeba\Panopticon\Library\Enumerations\ActionReportPeriod;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Library\View\FakeView;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Awf\Mvc\DataModel\Collection;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use DateTime;
use DateTimeZone;
use Exception;
use RuntimeException;

#[AsTask(
	name: 'actionsummaryemail',
	description: 'PANOPTICON_TASKTYPE_ACTIONSUMMARYEMAIL'
)]
class ActionSummaryEmail extends AbstractCallback
{
	use EmailSendingTrait;
	use LanguageListTrait;
	use SiteNotificationEmailTrait;

	/**
	 * The site we are reporting updates for.
	 *
	 * @var    Site|null
	 * @since  1.0.5
	 */
	private ?Site $site = null;

	private ?ActionReportPeriod $period = null;

	/**
	 * The user group to send emails to.
	 *
	 * When this is set, emails will be sent to all users of the selected group regardless of whether they can see or
	 * manage the site the report is generated for. If this is not set (empty array) emails will be sent to any user
	 * who has the Super global privilege and/or is allowed to manage the site.
	 *
	 * @var array|int[]
	 */
	private array $emailGroups;

	public function __invoke(object $task, Registry $storage): int
	{
		$this->initialiseObject($task);

		[$start, $end, $tz] = $this->getDateTimeRange();

		$this->logger->info(
			sprintf(
				'Sending a Site Action Summary email for site #%d (%s)',
				$this->site->getId(),
				$this->site->name
			)
		);
		$this->logger->debug(
			sprintf(
				'The selected period is ‘%s’ (%s) in the %s timezone',
				$this->period->value,
				$this->period->describe(),
				$tz->getName()
			)
		);
		$this->logger->debug(
			sprintf(
				'The selected period is between %s to %s',
				(clone $start)->setTimezone($tz)->format(DATE_COOKIE),
				(clone $end)->setTimezone($tz)->format(DATE_COOKIE)
			)
		);
		$this->logger->debug(
			sprintf(
				'Selecting database entries logged between %s and %s',
				$start->format('Y-m-d H:i:s T'),
				$end->format('Y-m-d H:i:s T')
			)
		);

		// Get report entries for this site and time period
		/** @var Reports $model */
		$model = $this->getContainer()->mvcFactory->makeTempModel('Reports');
		$model->setState('site_id', $this->site->id);
		$model->setState('from_date', $this->getContainer()->dateFactory($start->format(DATE_RFC3339), 'GMT'));
		$model->setState('to_date', $this->getContainer()->dateFactory($end->format(DATE_RFC3339), 'GMT'));
		$records = $model->get(true);

		if ($records->count() === 0)
		{
			$this->logger->notice('There are no Site Action records for the selected site and time period.');
		}
		else
		{
			$this->logger->debug(
				sprintf(
					'Found %d Site Action record(s) for the selected site and time period.',
					$records->count()
				)
			);
		}

		$perLanguageVars = [];

		foreach ($this->getAllKnownLanguages() as $language)
		{
			[$rendered, $renderedText] = $this->getRenderedResultsForEmail(
				$language, $records, $this->site, $start, $end
			);

			if (empty($rendered))
			{
				continue;
			}

			$perLanguageVars[$language] = [
				'RENDERED_HTML' => $rendered,
				'RENDERED_TEXT' => $renderedText,
			];
		}

		[$rendered, $renderedText] = $this->getRenderedResultsForEmail('', $records, $this->site, $start, $end);
		$emailKey  = 'action_summary';
		$variables = [
			'SITE_NAME'     => $this->site->name,
			'SITE_URL'      => $this->site->getBaseUrl(),
			'RENDERED_HTML' => $rendered,
			'RENDERED_TEXT' => $renderedText,
		];

		// Get the CC email addresses
		$config = $this->site->getFieldValue('config', '{}');
		$config = ($config instanceof Registry) ? $config->toString() : $config;

		try
		{
			$config = @json_decode($config);
		}
		catch (Exception $e)
		{
			$config = null;
		}

		$cc = $this->getSiteNotificationEmails($config);

		$data = new Registry();
		$data->set('template', $emailKey);
		$data->set('email_variables', $variables);
		$data->set('email_variables_by_lang', $perLanguageVars);
		$data->set('permissions', ['panopticon.super', 'panopticon.admin', 'panopticon.editown']);
		$data->set('email_cc', $cc);

		if (!empty($this->emailGroups))
		{
			// Email groups selected. Send the emil ONLY to users belonging in these groups.
			$data->set('email_cc', []);
			$data->set('permissions', []);
			$data->set('email_groups', $this->emailGroups);
			$data->set('only_email_groups', true);
		}

		$this->enqueueEmail($data, $this->site->id, 'now');

		return Status::OK->value;
	}

	/**
	 * Initialise the object from the task parameters
	 *
	 * @param   object  $task
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	private function initialiseObject(object $task): void
	{
		$params = ($task->params instanceof Registry) ? $task->params : new Registry($task->params ?? null);

		$this->site   = $this->getSite($task);
		$this->period = ActionReportPeriod::tryFrom($params->get('period', 'daily') ?: 'daily') ??
		                ActionReportPeriod::DAILY;

		$mailGroups                   = $params->get('email_groups', null) ?? null;
		$mailGroups                   = is_array($mailGroups) ? array_filter(ArrayHelper::toInteger($mailGroups)) : [];
		$this->emailGroups            = $mailGroups;
	}

	/**
	 * Retrieves the start and end date-time range based on the provided period.
	 *
	 * @return  array{DateTime, DateTime, DateTimeZone}  An array containing the start and end date-time objects in GMT
	 *                          time zone.
	 * @since   1.1.0
	 */
	private function getDateTimeRange()
	{
		$timeZone = $this->getContainer()->appConfig->get('timezone', 'UTC') ?: 'UTC';

		try
		{
			$tz = new DateTimeZone($timeZone);
		}
		catch (Exception $e)
		{
			$tz = new DateTimeZone('UTC');
		}

		$now = new DateTime('now', $tz);

		switch ($this->period)
		{
			case ActionReportPeriod::DAILY:
				$start = (clone $now)->sub(new \DateInterval('P1D'))->setTime(0, 0, 0, 0);
				$end   = (clone $start)->setTime(23, 59, 59, 999);
				break;

			case ActionReportPeriod::WEEKLY:
				$start   = (clone $now)->sub(new \DateInterval('P1W'))->setTime(0, 0, 0, 0);
				$weekDay = (int) $start->format('w');

				if ($weekDay > 0)
				{
					$start = $start->sub(new \DateInterval('P' . $weekDay . 'D'));
				}

				$end = (clone $start)->add(new \DateInterval('P1W'))
					->sub(new \DateInterval('P1D'))
					->setTime(23, 59, 59, 999);

				break;

			case ActionReportPeriod::MONTHLY:
				$start = (clone $now)
					->setDate($now->format('y'), $now->format('n'), 1)
					->sub(new \DateInterval('P1M'))
					->setTime(0, 0, 0, 0);

				$end = (clone $start)
					->add(new \DateInterval('P1M'))
					->sub(new \DateInterval('P1D'))
					->setTime(23, 59, 59, 59);
				break;
		}

		$gmt = new DateTimeZone('GMT');

		return [
			$start->setTimezone($gmt),
			$end->setTimezone($gmt),
			$tz,
		];
	}

	/**
	 * Get the site object corresponding to the task we are currently handling.
	 *
	 * @param   object  $task
	 *
	 * @return  Site
	 * @since   1.1.0
	 */
	private function getSite(object $task): Site
	{
		// Do we have a site?
		if ($task->site_id <= 0)
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_TASK_JOOMLAUPDATE_ERR_SITE_INVALIDID'));
		}

		// Does the site exist?
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->find($task->site_id);

		if ($site->id != $task->site_id)
		{
			throw new RuntimeException(
				$this->getLanguage()->sprintf(
					'PANOPTICON_TASK_JOOMLAUPDATE_ERR_SITE_NOT_EXIST',
					$task->site_id
				)
			);
		}

		return $site;
	}

	private function getRenderedResultsForEmail(
		string $language, array|Collection $records, Site $site, DateTime $start, DateTime $end
	): array
	{
		$template                = 'Mailtemplates/mail_action_summary' . (empty($language) ? '' : '.') . $language;
		$container               = clone $this->container;
		$container['mvc_config'] = [
			'template_path' => [
				APATH_ROOT . '/ViewTemplates/Mailtemplates',
				APATH_USER_CODE . '/ViewTemplates/Mailtemplates',
			],
		];
		$container->language->loadLanguage($language ?: $container->appConfig->get('language', 'en-GB'));
		$fakeView                = new FakeView($container, ['name' => 'Mailtemplates']);

		try
		{
			$rendered = $fakeView->loadAnyTemplate(
				$template,
				[
					'records' => $records,
					'site'    => $site,
					'start'   => $start,
					'end'     => $end,
				]
			);
		}
		catch (Exception $e)
		{
			// Expected, as the language override may not be in place.
			return ['', ''];
		}

		// Render the messages as plain text
		$template     = 'Mailtemplates/mail_action_summary' . (empty($language) ? '' : '.') . $language
		                            . 'text';
		$renderedText = '';

		try
		{
			$renderedText = $fakeView->loadAnyTemplate(
				$template,
				[
					'records' => $records,
					'site'    => $site,
					'start'   => $start,
					'end'     => $end,
				]
			);
		}
		catch (Exception $e)
		{
			// Expected, as the language override may not be in place.
			$renderedText = (new Html2Text($rendered))->getText();
		}

		return [$rendered, $renderedText];
	}
}