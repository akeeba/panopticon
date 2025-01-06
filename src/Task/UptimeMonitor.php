<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\LanguageListTrait;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\SaveSiteTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Akeeba\Panopticon\View\Trait\TimeAgoTrait;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use Exception;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Uptime monitoring
 *
 * @since  1.1.0
 */
#[AsTask(
	name: 'uptimemonitor',
	description: 'PANOPTICON_TASKTYPE_UPTIMEMONITOR'
)]
class UptimeMonitor extends AbstractCallback
{
	use SaveSiteTrait;
	use EmailSendingTrait;
	use SiteNotificationEmailTrait;
	use LanguageListTrait;
	use TimeAgoTrait;

	public function __invoke(object $task, Registry $storage): int
	{
		// Is uptime monitoring enabled at all?
		$enabled = $this->getContainer()->appConfig->get('uptime', 'none') === 'panopticon';

		if (!$enabled)
		{
			$this->logger->debug(
				'Uptime monitoring using Panopticon\'s own code is disabled in System Configuration. Exiting.'
			);

			return Status::OK->value;
		}

		$task->params ??= new Registry();
		$params       = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);

		$limitStart = (int) $storage->get('limitStart', 0);
		$limit      = (int) $storage->get('limit', $params->get('limit', 50));
		$filterIDs  = $storage->get('filter.ids', $params->get('ids', []));

		$siteIDs = $this->getSiteIDsForFetchInfo(
			limitStart: $limitStart,
			limit: $limit,
			onlyTheseIDs: $filterIDs,
		);

		if (empty($siteIDs))
		{
			$this->logger->info('No sites to check for uptime.');

			return Status::OK->value;
		}

		$this->logger->info(
			sprintf(
				'Found a further %d site(s) to check for uptime.',
				count($siteIDs)
			)
		);

		$this->checkUptimeOfSiteIDs($siteIDs);

		$storage->set('limitStart', $limitStart + $limit);

		return Status::WILL_RESUME->value;
	}

	private function getSiteIDsForFetchInfo(
		int $limitStart, int $limit, array $onlyTheseIDs = []
	): array
	{
		$db = $this->container->db;

		/**
		 * Reasoning behind this code:
		 *
		 * “The correct way to use LOCK TABLES and UNLOCK TABLES with transactional tables, such as InnoDB tables, is to
		 * begin a transaction with SET autocommit = 0 (not START TRANSACTION) followed by LOCK TABLES, and to not call
		 * UNLOCK TABLES until you commit the transaction explicitly.”
		 *
		 * This is meant to avoid deadlocks.
		 *
		 * @see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
		 */
		$db->setQuery('SET autocommit = 0')->execute();
		$db->lockTable('#__sites');

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__sites'))
			->where($db->quoteName('enabled') . ' = 1');

		$query->andWhere(
			[
				$query->jsonExtract($db->quoteName('config'), '$.uptime.enable') . ' IS NULL',
				$query->jsonExtract($db->quoteName('config'), '$.uptime.enable') . ' = 1',
			]
		);

		$onlyTheseIDs = ArrayHelper::toInteger($onlyTheseIDs, []);

		if (!empty($onlyTheseIDs))
		{
			$query->andWhere(
				$db->quoteName('id') . 'IN (' . implode(',', $onlyTheseIDs) . ')'
			);
		}

		$siteIDs = $db->setQuery($query, $limitStart, $limit)->loadColumn() ?: [];
		$siteIDs = array_filter(ArrayHelper::toInteger($siteIDs, []));

		// For the reasoning of this code see https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html
		$db->setQuery('COMMIT')->execute();
		$db->unlockTables();
		$db->setQuery('SET autocommit = 1')->execute();

		return $siteIDs;
	}

	private function checkUptimeOfSiteIDs(array $siteIDs): void
	{
		$siteIDs = ArrayHelper::toInteger($siteIDs, []);

		if (empty($siteIDs))
		{
			return;
		}

		$httpClient = $this->container->httpFactory->makeClient(cache: false);

		$promises = array_map(
			function (int $id) use ($httpClient) {
				/** @var Site $site */
				$site = $this->getContainer()->mvcFactory->makeTempModel('Sites');

				try
				{
					$site->findOrFail($id);
				}
				catch (\RuntimeException $e)
				{
					return null;
				}

				$this->logger->debug(
					sprintf(
						'Enqueueing site #%d (%s) for uptime monitoring.',
						$site->id, $site->name
					)
				);

				$options                                  = $this->container->httpFactory->getDefaultRequestOptions();
				$options[RequestOptions::HEADERS]         = [
					'User-Agent' => 'panopticon/' . AKEEBA_PANOPTICON_VERSION,
				];
				$options[RequestOptions::TIMEOUT]         = 15;
				$options[RequestOptions::CONNECT_TIMEOUT] = 5;
				$options[RequestOptions::ALLOW_REDIRECTS] = true;

				$config = $site->getConfig();
				$url    = $site->getBaseUrl();
				$path   = trim($config->get('uptime.path', ''));

				if (!empty($path))
				{
					$url .= '/' . ltrim($path, '/');
				}

				return $httpClient
					// See https://docs.guzzlephp.org/en/stable/quickstart.html#async-requests and https://docs.guzzlephp.org/en/stable/request-options.html
					->getAsync($url, $options)
					->then(
						function (ResponseInterface $response) use ($site) {
							$statusCode = $response->getStatusCode();

							if ($statusCode < 200 || $statusCode > 299)
							{
								// Throwing here triggers the next otherwise() handler
								throw new \RuntimeException(sprintf('HTTP %d', $statusCode));
							}

							$checkFor = trim($site->getConfig()->get('uptime.string', ''));

							if (empty($checkFor))
							{
								return $response;
							}

							$body = strip_tags($response->getBody()->getContents());
							$body = str_replace(["\n", "\r"], ['', ''], $body);
							$body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

							if (function_exists('mb_convert_encoding'))
							{
								$body = preg_replace_callback(
									"/(&#[0-9]+;)/", fn($m) => mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"),
									$body
								);
							}

							$body = preg_replace('\s+', ' ', $body);

							if (stripos($body, $checkFor) === false)
							{
								throw new \RuntimeException('Search string not found');
							}

							return $response;
						},
						function (Throwable $e) use ($site) {
							// Rethrow the exception to trigger otherwise() below
							throw $e;
						}
					)
					->then(
						function (ResponseInterface $response) use ($site) {
							// Log the result
							$this->logger->debug(
								sprintf(
									'Site #%d (%s) up',
									$site->getId(),
									$site->getName()
								)
							);

							// Note down whether (and since when) the site has been down
							$downSince = $site->getConfig()->get('uptime.downtime_start', null);

							// Ensure the site appears as "up"
							$this->saveSite(
								$site,
								function (Site $tempSite) {
									$config = $tempSite->getConfig();

									$config->set('uptime.downtime_start', null);

									$tempSite->config = $config->toString();
								}
							);

							// Did the site just go up?
							if (!empty($downSince))
							{
								// Log site action: site is back up
								Reports::fromSiteUptime($site->getId(), true, $downSince);

								// Send email: site is back up
								$this->sendEmail('site_up', $site, $downSince);

								// Trigger event
								$this->getContainer()->eventDispatcher->trigger(
									'onSiteIsBackUp', [$site, $downSince]
								);
							}
						}
					)
					->otherwise(
						function (Throwable $e) use ($site) {
							// Log the result
							$this->logger->debug(
								sprintf(
									'Site #%d (%s) down: %s',
									$site->getId(),
									$site->getName(),
									$e->getMessage()
								)
							);

							// Note down whether (and since when) the site has been down
							$downSince = $site->getConfig()->get('uptime.downtime_start', null);

							// Ensure the site appears as "down"
							$this->saveSite(
								$site,
								function (Site $tempSite) {
									$config = $tempSite->getConfig();

									$config->set('uptime.downtime_start', time());

									$tempSite->config = $config->toString();
								}
							);

							// Did the site just go down?
							if (empty($downSince))
							{
								// Log site action: site has gone down
								Reports::fromSiteUptime($site->getId(), false, null, $e);

								// Send email: site has gone down
								$this->sendEmail('site_down', $site);

								// Trigger event
								$this->getContainer()->eventDispatcher->trigger(
									'onSiteHasGoneDown', [$site]
								);
							}
						}
					);
			},
			$siteIDs
		);

		$promises = array_filter($promises);

		if (empty($promises))
		{
			return;
		}

		Utils::settle($promises)->wait(true);
	}

	private function sendEmail(
		string $mailTemplate,
		Site $site,
		?int $downSince = null,
		array $permissions = [
			'panopticon.super',
			'panopticon.manage',
		]
	): void
	{
		$this->logger->debug(
			sprintf(
				'Enqueuing email template ‘%s’ for site %d (%s)',
				$mailTemplate, $site->id, $site->name
			)
		);

		$siteConfig = $site->getConfig() ?? new Registry();

		$variables            = [
			'SITE_NAME'        => $site->name,
			'SITE_URL'         => $site->getBaseUrl(),
			'DOWNTIME_START'   => '',
			'DOWNTIME_HUMAN'   => '',
			'DOWNTIME_SECONDS' => 0,
		];
		$perLanguageVariables = [];

		if ($downSince)
		{
			$variables['DOWNTIME_SECONDS'] = time() - $downSince;
			$variables['DOWNTIME_START']   = $this->getContainer()->html->basic->date(
				$downSince,
				$this->getContainer()->language->text('DATE_FORMAT_LC2')
			);
			$variables['DOWNTIME_HUMAN']   = $this->timeAgo($downSince, autoSuffix: false);

			foreach ($this->getAllKnownLanguages() as $lang)
			{
				$langObject = $this->getContainer()->languageFactory($lang);

				$perLanguageVariables[$lang] = [
					'DOWNTIME_START' => $this->getContainer()->html->basic->date(
						$downSince,
						$langObject->text('DATE_FORMAT_LC2')
					),
					'DOWNTIME_HUMAN' => $this->timeAgo($downSince, autoSuffix: false, languageObject: $langObject),
				];
			}
		}

		try
		{
			$config = @json_decode($siteConfig->toString());
		}
		catch (Exception $e)
		{
			$config = null;
		}

		$cc = $this->getSiteNotificationEmails($config);

		$data = new Registry();
		$data->set('template', $mailTemplate);
		$data->set('email_variables', $variables);
		$data->set('email_variables_by_lang', $perLanguageVariables);
		$data->set('permissions', $permissions);
		$data->set('email_cc', $cc);

		$this->enqueueEmail($data, $site->id, 'now');
	}
}