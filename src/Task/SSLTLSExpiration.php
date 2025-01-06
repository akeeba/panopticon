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
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\Trait\EmailSendingTrait;
use Akeeba\Panopticon\Task\Trait\SiteNotificationEmailTrait;
use Awf\Registry\Registry;
use Awf\Utils\ArrayHelper;
use Exception;

/**
 * Handles the monitoring of SSL/TLS certificate expiration and sends notifications to site owners.
 *
 * @since   1.1.0
 */
#[AsTask(
	name: 'ssltlsexpiration',
	description: 'PANOPTICON_TASKTYPE_SSLTLSEXPIRATION'
)]
class SSLTLSExpiration extends AbstractCallback
{
	use LanguageListTrait;
	use SiteNotificationEmailTrait;
	use EmailSendingTrait;

	/** @inheritDoc */
	public function __invoke(object $task, Registry $storage): int
	{
		$task->params ??= new Registry();
		$params       = ($task->params instanceof Registry) ? $task->params : new Registry($task->params);

		$limitStart = (int) $storage->get('limitStart', 0);
		$limit      = (int) $storage->get('limit', $params->get('limit', 100));
		$force      = (bool) $storage->get('force', $params->get('force', false));
		$filterIDs  = $storage->get('filter.ids', $params->get('ids', []));
		$warnDays   = $storage->get('warnDays', $params->get('warnDays', null));

		$siteIDs = $this->getSiteIDs($limitStart, $limit, $force, $filterIDs, $warnDays);

		if (empty($siteIDs))
		{
			$this->logger->info('No sites to process SSL/TLS expiration for');

			return Status::OK->value;
		}

		$this->logger->info(
			sprintf(
				'Found a further %d site(s) to notify about SSL/TLS certificate expiration.',
				count($siteIDs)
			)
		);

		$siteIDs = ArrayHelper::toInteger($siteIDs, []);

		foreach ($siteIDs as $site_id)
		{
			// Get the site object
			/** @var Site $site */
			$site = $this->container->mvcFactory->makeTempModel('Site');

			try
			{
				$site->findOrFail($site_id);
			}
			catch (Exception)
			{
				continue;
			}

			$this->logger->info(
				sprintf(
					'Sending email for site #%s (%s) - certificate expiring on %s',
					$site->getId(),
					$site->name,
					$site->getConfig()->get('ssl.validTo') ?? '(unknown)'
				)
			);

			$config       = $site->getConfig();
			$site->config = $config->set(
				'auto_email.ssl.serial',
				$config->get('ssl.serialHex')
			);

			// Save the configuration (three tries)
			$retry = -1;

			do
			{
				try
				{
					$retry++;

					$site->save(
						[
							'config' => $config->toString(),
						]
					);

					break;
				}
				catch (Exception $e)
				{
					if ($retry >= 3)
					{
						$this->logger->error(
							sprintf(
								'Error saving the information for site #%d (%s) before sending SSL/TLS expiration warning email: %s',
								$site->id, $site->name, $e->getMessage()
							)
						);

						break;
					}

					$this->logger->warning(
						sprintf(
							'Failed saving the information for site #%d (%s) before sending SSL/TLS expiration warning email (will retry): %s',
							$site->id, $site->name, $e->getMessage()
						)
					);

					sleep($retry);
				}
			} while ($retry < 3);

			// Certificate has expired, send notification to the user
			$this->sendExpirationNotification($site);
		}

		if ($force)
		{
			$storage->set('limitStart', $limitStart + count($siteIDs));
		}

		return Status::WILL_RESUME->value;
	}

	/**
	 * Retrieves an array of IDs of sites with expiring certificates.
	 *
	 * @param   int       $limitStart  The starting position of the result set. Should be 0.
	 * @param   int       $limit       The maximum number of results to retrieve.
	 * @param   bool      $force       Return sites even if we've already sent an email for the current certificate.
	 * @param   mixed     $filterIDs   Limit results within these site IDs.
	 * @param   int|null  $warnDays    An optional number of days to check against the SSL expiration date. If null, a default value of 7 will be used.
	 *
	 * @return  array  An array containing the retrieved site IDs.
	 * @since   1.1.0
	 */
	private function getSiteIDs(int $limitStart, int $limit, bool $force, mixed $filterIDs, ?int $warnDays): array
	{
		$db = $this->getContainer()->db;

		/**
		 * SELECT `id`
		 * FROM #__sites
		 * WHERE
		 * JSON_EXTRACT(config, '$.ssl.validTo') IS NOT NULL
		 * AND
		 * DATE_SUB(JSON_EXTRACT(config, '$.ssl.validTo'), INTERVAL CAST(IFNULL(JSON_EXTRACT(config, '$.config.ssl.warning'), 7) AS UNSIGNED) DAY) <= NOW()
		 */
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__sites'))
			->where($db->quoteName('enabled') . ' = 1');

		$query->where(
			$query->jsonExtract('config', '$.ssl.validTo') . ' IS NOT NULL'
		);

		if (is_int($warnDays))
		{
			$query->where(
				'DATE_SUB(CAST(' . $query->jsonExtract('config', '$.ssl.validTo') . ' AS DATETIME), INTERVAL '
				. $warnDays . ' DAY) <= NOW()'
			);
		}
		else
		{
			$query->where(
				'DATE_SUB(CAST(' . $query->jsonExtract('config', '$.ssl.validTo') . ' AS DATETIME), INTERVAL '
				. 'CAST(IFNULL(' . $query->jsonExtract('config', '$.config.ssl.warning') . ', 7) AS FLOAT) DAY)'
				. ' <= NOW()'
			);
		}

		if (!$force)
		{
			$query->extendWhere(
				'AND', [
				$query->jsonExtract('config', '$.auto_email.ssl.serial') . ' IS NULL',
				$query->jsonExtract('config', '$.auto_email.ssl.serial') . ' != ' . $query->jsonExtract(
					'config', '$.ssl.serialHex'
				),
			], 'OR'
			);
		}

		$filterIDs = ArrayHelper::toInteger($filterIDs, []);

		if (!empty($filterIDs))
		{
			$query->andWhere(
				$db->quoteName('id') . 'IN (' . implode(',', $filterIDs) . ')'
			);
		}

		$siteIDs = $db->setQuery($query, $limitStart, $limit)->loadColumn() ?: [];

		return array_filter(ArrayHelper::toInteger($siteIDs, []));
	}

	/**
	 * Sends expiration notification email for a given site.
	 *
	 * @param   Site  $site  The site to send the notification for.
	 *
	 * @return  void
	 * @since   1.1.0
	 */
	private function sendExpirationNotification(Site $site): void
	{
		// Ensure there are not stray transactions
		try
		{
			$this->container->db->transactionCommit();
		}
		catch (Exception)
		{
			// Okay...
		}

		$perLanguageVariables = [];
		$basicHtmlHelper      = $this->container->html->basic;
		$siteConfig           = $site->getConfig();
		$validTo              = $siteConfig->get('ssl.validTo');
		$validFrom            = $siteConfig->get('ssl.validFrom');

		foreach ($this->getAllKnownLanguages() as $lang)
		{
			$langObject = $this->getContainer()->languageFactory($lang);

			$perLanguageVariables[$lang] = [
				'DATE'          => $validTo ? $basicHtmlHelper->date(
					$validTo,
					$langObject->text('DATE_FORMAT_LC7')
				) : '',
				'DATE_FROM'     => $validFrom ? $basicHtmlHelper->date(
					$validFrom,
					$langObject->text('DATE_FORMAT_LC7')
				) : '',
				'CERT_VERIFIED' => $langObject->text($siteConfig->get('ssl.verified') ? 'AWF_YES' : 'AWF_NO'),
			];
		}

		$data = new Registry();
		$data->set('template', 'ssl_tls_expiring');
		$data->set(
			'email_variables', [
				// Necessary for the default language
				'DATE'          => $validTo ? $basicHtmlHelper->date(
					$validTo,
					$this->getLanguage()->text('DATE_FORMAT_LC7')
				) : '',
				'DATE_FROM'     => $validFrom ? $basicHtmlHelper->date(
					$validFrom,
					$this->getLanguage()->text('DATE_FORMAT_LC7')
				) : '',
				'CERT_VERIFIED' => $this->getLanguage()->text($siteConfig->get('ssl.verified') ? 'AWF_YES' : 'AWF_NO'),
				// Untranslated
				'SITE_NAME'       => $site->name,
				'SITE_URL'        => $site->getBaseUrl(),
				'CERT_HASH'       => $siteConfig->get('ssl.hash'),
				'CERT_SERIAL'     => $siteConfig->get('ssl.serialHex'),
				'CERT_TYPE'       => $siteConfig->get('ssl.type'),
				'CERT_VALID_FROM' => $siteConfig->get('ssl.validFrom'),
				'CERT_VALID_TO'   => $siteConfig->get('ssl.validTo'),
				'CERT_CN'         => implode(', ', $siteConfig->get('ssl.commonName', [])),
				'CERT_ISSUER_CN'  => $siteConfig->get('ssl.issuerCommonName'),
				'CERT_ISSUER_ORG' => $siteConfig->get('ssl.issuerOrganisation'),
			]
		);
		$data->set('email_variables_by_lang', $perLanguageVariables);
		$data->set('permissions', ['panopticon.super', 'panopticon.admin', 'panopticon.editown']);
		$data->set('email_cc', $this->getSiteNotificationEmails($siteConfig->toObject()));

		$this->enqueueEmail($data, $site->id, 'now');
	}


}