<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Exception\SiteConnection\cURLError;
use Akeeba\Panopticon\Exception\SiteConnection\InvalidHostName;
use Akeeba\Panopticon\Exception\SiteConnection\SelfSignedSSL;
use Akeeba\Panopticon\Exception\SiteConnection\SSLCertificateProblem;
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Log;
use Awf\Uri\Uri;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Joomla Update Doctor.
 *
 * Diagnoses why a Joomla! core update run fails or gets stuck. Unlike the Connection Doctor (which
 * exercises the JSON API), this probes the parts of the update flow the API cannot see: the direct
 * request to the site's `extract.php` extraction script, plus the site's Joomla update task log and
 * the latest core update report.
 *
 * @see SiteTestConnectionJoomlaTrait  the sibling trait whose API probe we reuse as a precondition
 */
trait SiteJoomlaUpdateDoctorTrait
{
	/**
	 * The session prefix used for the debug HTTP/exception dump of the extraction endpoint probe.
	 *
	 * The `troubleshoot_update` Blade template reads these keys back to render the raw dump.
	 */
	protected string $updateDoctorSessionPrefix = 'updatedoctor.';

	/**
	 * Run the Joomla Update Doctor checks for this site.
	 *
	 * @return  object  A structured result the `updatedoctor` / `troubleshoot_update` templates switch on.
	 */
	public function runUpdateDoctor(): object
	{
		$result = (object) [
			// API reachability precondition
			'apiUnreachable' => false,
			'apiError'       => null,
			// extract.php probe
			'extract'        => null,
			// log signature scan
			'logMissing'     => true,
			'logFileName'    => 'joomlaupdate.' . $this->id . '.log',
			'logFinding'     => null,
			// latest core update report (structured corroboration)
			'report'         => null,
			// global logging verbosity, so the template can offer the "enable debug logging" action
			'logLevelDebug'  => strtolower((string) $this->getContainer()->appConfig->get('log_level', 'warning')) === 'debug',
			// overall verdict
			'ok'             => false,
		];

		// This doctor is Joomla-specific (the extraction endpoint does not exist on WordPress).
		if ($this->cmsType() !== CMSType::JOOMLA)
		{
			$result->apiUnreachable = true;

			return $result;
		}

		// 1. API reachability precondition. If the API is down, the Connection Doctor is the right tool.
		try
		{
			$this->testConnectionJoomla(false);
		}
		catch (Throwable $e)
		{
			$result->apiUnreachable = true;
			$result->apiError       = $e;

			return $result;
		}

		// 2. Probe the extraction endpoint (extract.php / restore.php).
		$result->extract = $this->probeExtractionEndpoint();

		// 3. Scan the site's Joomla update task log for known failure signatures.
		$this->scanUpdateLog($result);

		// 4. Corroborate with the latest core update report.
		$result->report = $this->getLatestCoreUpdateReport();

		// Overall verdict: reachable API, extraction endpoint OK, and no error found in the log.
		$result->ok = !$result->apiUnreachable
		              && ($result->extract?->status === 'ok')
		              && $result->logFinding === null;

		return $result;
	}

	/**
	 * Probe the Joomla Update extraction endpoint the same way the JoomlaUpdate task does.
	 *
	 * Mirrors {@see \Akeeba\Panopticon\Task\JoomlaUpdate::getExtractUrl()} and ::doExtractAjax(): a POST
	 * to `/administrator/components/com_joomlaupdate/extract.php` with a deliberately wrong password and
	 * the admin-folder HTTP authentication. A reachable, healthy endpoint answers HTTP 200 with a JSON
	 * `{status:false, message:"Invalid login"}`; anything else tells us what is blocking the update.
	 *
	 * @return  object  { status: 'ok'|'auth'|'blocked'|'error', httpStatus, exception, endpoint, isRestore }
	 */
	protected function probeExtractionEndpoint(): object
	{
		$out = (object) [
			'status'     => 'error',
			'httpStatus' => null,
			'exception'  => null,
			'endpoint'   => '',
			'isRestore'  => false,
		];

		$version           = $this->getConfig()->get('core.current.version', '4.0.4');
		$out->isRestore    = version_compare((string) $version, '4.0.3', 'le');
		$scriptName        = $out->isRestore ? 'restore.php' : 'extract.php';
		$out->endpoint     = $this->getBaseUrl() . '/administrator/components/com_joomlaupdate/' . $scriptName;

		$client  = $this->container->httpFactory->makeClient(cache: false, singleton: false);
		$options = $this->container->httpFactory->getDefaultRequestOptions();

		$options[RequestOptions::HEADERS]               ??= [];
		$options[RequestOptions::HEADERS]['User-Agent'] = 'panopticon/' . AKEEBA_PANOPTICON_VERSION;
		$options[RequestOptions::HTTP_ERRORS]           = false;

		$totalTimeout                             = max(30, $this->container->appConfig->get('max_execution', 60) / 2);
		$options[RequestOptions::TIMEOUT]         = $totalTimeout;
		$options[RequestOptions::CONNECT_TIMEOUT] = max(5, $totalTimeout / 5);

		// Administrator-folder HTTP authentication (protects the /administrator directory)
		$config   = $this->getConfig();
		$username = $config->get('config.diaxeiristis_onoma');
		$password = $config->get('config.diaxeiristis_sunthimatiko');

		if (!empty($username))
		{
			$options[RequestOptions::AUTH] = [$username, $password];
		}

		$options[RequestOptions::FORM_PARAMS] = [
			'task'        => 'startExtract',
			// A deliberately wrong password: a healthy endpoint rejects it with a JSON "Invalid login".
			'password'    => 'panopticon-doctor-probe-' . bin2hex(random_bytes(8)),
			'_randomJunk' => hash('sha1', random_bytes(32)),
		];

		try
		{
			$response = $client->post($out->endpoint, $options);
		}
		catch (GuzzleException $e)
		{
			$this->updateDebugInfoInSession(null, null, $e, $this->updateDoctorSessionPrefix);

			$out->status    = 'error';
			$out->exception = $this->mapConnectionException($e);

			return $out;
		}

		$body            = $response->getBody()->getContents();
		$out->httpStatus = $response->getStatusCode();

		$this->updateDebugInfoInSession($response, $body, null, $this->updateDoctorSessionPrefix);

		if ($out->httpStatus === 401)
		{
			// The admin folder is HTTP-password protected and our credentials are missing/wrong.
			$out->status = 'auth';
		}
		elseif ($out->httpStatus === 200)
		{
			// Reachable. (The wrong password is expected to be rejected with a JSON body; even when
			// update.php is absent the script still answers HTTP 200, so 200 == the path is not blocked.)
			$out->status = 'ok';
		}
		else
		{
			// 403, 509, 5xx, … — blocked upstream (WAF, host GeoIP filter, rate/bandwidth limit, CDN).
			$out->status = 'blocked';
		}

		return $out;
	}

	/**
	 * Map a Guzzle transport exception to the shared SiteConnection exception types, so the extraction
	 * probe gives the same TLS/DNS/cURL advice as the Connection Doctor.
	 */
	protected function mapConnectionException(GuzzleException $e): Throwable
	{
		$message = $e->getMessage();

		if (str_contains($message, 'self-signed certificate'))
		{
			return new SelfSignedSSL('Self-signed certificate', previous: $e);
		}

		if (str_contains($message, 'SSL certificate problem'))
		{
			return new SSLCertificateProblem('SSL certificate problem', previous: $e);
		}

		if (str_contains($message, 'Could not resolve host'))
		{
			$hostname = empty($this->url) ? '(no host provided)' : (new Uri($this->url))->getHost();

			return new InvalidHostName(sprintf('Invalid hostname %s', $hostname));
		}

		if (str_contains($message, 'cURL error'))
		{
			return new cURLError('Miscellaneous cURL Error', previous: $e);
		}

		return $e;
	}

	/**
	 * Scan the site's Joomla update task log for the newest recognised failure signature.
	 *
	 * Populates `$result->logMissing` and `$result->logFinding` (a `{category, message, httpCode,
	 * timestamp}` object, or null when nothing actionable is found).
	 *
	 * The signatures are matched against the English (en-GB) task log messages — the only officially
	 * maintained language and the one virtually all logs are written in.
	 */
	protected function scanUpdateLog(object $result): void
	{
		/** @var Log $logModel */
		$logModel = $this->getContainer()->mvcFactory->makeTempModel('Log');

		if (empty($logModel->getVerifiedLogFilePath($result->logFileName)))
		{
			$result->logMissing = true;

			return;
		}

		$result->logMissing = false;

		// The task log is append-only and contains every past run for this site. Read a large window so
		// the whole of the current (rotated at ~1 MiB) log is captured, including the start marker of the
		// most recent attempt even when Debug logging makes a single attempt hundreds of lines long.
		$logModel->setState('size', 4 * 1024 * 1024);
		$logModel->setState('lines', 10000);

		$lines = $logModel->getLogLines($result->logFileName);

		if (empty($lines))
		{
			return;
		}

		// Bound the analysis to the MOST RECENT update attempt only. Each attempt begins with an INFO
		// "Preparing to update site …" marker; everything before the latest such marker belongs to older
		// runs and must be ignored. $lines is newest-first, so the first marker we meet scanning from the
		// top is the start of the latest attempt; keep the newest line down to (and including) that marker.
		foreach ($lines as $i => $line)
		{
			if (str_contains((string) ($line->message ?? ''), 'Preparing to update site'))
			{
				$lines = array_slice($lines, 0, $i + 1);

				break;
			}
		}

		// Work in chronological (ascending) order within the attempt, so the FIRST error we find is the
		// root failure rather than a later cascade/cleanup message.
		$attempt = array_reverse($lines);

		// category => regular expression matched against the (English) log message text.
		$signatures = [
			'extraction_blocked' => '/Unexpected HTTP\s+(\d+)\s+while extracting/i',
			'admin_auth'         => '/administrator folder is password protected|HTTP status 401 while extracting/i',
			'invalid_json'       => '/Invalid JSON response from the update extraction script/i',
			'extraction_failed'  => '/The update extraction has failed/i',
			// NOTE: match only the genuine download failure. Do NOT match "The multi-part download …
			// has failed; will try a single-part download instead" — that is a benign NOTICE where the
			// task deliberately falls back to a single-part download and then succeeds.
			'download_failed'    => '/Downloading the update package has failed/i',
			'invalid_checksum'   => '/invalid checksum/i',
			'backup_failed'      => '/Failed to take a backup before updating/i',
			'enable_failed'      => '/Enabling the Joomla Update extraction script has failed/i',
			'update_disappeared' => '/update package has disappeared/i',
			'finalise_failed'    => '/Finalising the update has failed/i',
			'reload_failed'      => '/reload the Joomla update information/i',
			'factory'            => '/internal state of the update extraction script/i',
		];

		$errorLevels = [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY];

		// Find the earliest ERROR/CRITICAL line in the attempt that matches a known signature. We only
		// consider error-level lines on purpose: every genuine update failure is thrown and logged at
		// CRITICAL, whereas benign progress messages (e.g. "The multi-part download … has failed; will try
		// a single-part download instead") are NOTICE/INFO and must never be treated as failures.
		foreach ($attempt as $line)
		{
			if (!in_array($line->loglevel ?? null, $errorLevels, true))
			{
				continue;
			}

			$message = (string) ($line->message ?? '');

			foreach ($signatures as $category => $pattern)
			{
				if (!preg_match($pattern, $message, $matches))
				{
					continue;
				}

				$result->logFinding = (object) [
					'category'  => $category,
					'message'   => $message,
					'httpCode'  => ($category === 'extraction_blocked' && isset($matches[1])) ? (int) $matches[1] : null,
					'timestamp' => $line->timestamp ?? null,
				];

				return;
			}
		}

		// No known signature matched. Surface the earliest error/critical line of the attempt verbatim.
		foreach ($attempt as $line)
		{
			if (!in_array($line->loglevel ?? null, $errorLevels, true))
			{
				continue;
			}

			$result->logFinding = (object) [
				'category'  => 'unknown',
				'message'   => (string) ($line->message ?? ''),
				'httpCode'  => null,
				'timestamp' => $line->timestamp ?? null,
			];

			return;
		}

		// No error line in the latest attempt. If the task table says it timed out, the worker was likely
		// killed mid-step; report that using the last line the attempt managed to write.
		$task = $this->getJoomlaUpdateTask();

		if ($task !== null && (int) $task->last_exit_code === Status::TIMEOUT->value)
		{
			$newest             = end($attempt) ?: null;
			$result->logFinding = (object) [
				'category'  => 'timeout',
				'message'   => (string) ($newest->message ?? ''),
				'httpCode'  => null,
				'timestamp' => $newest->timestamp ?? null,
			];
		}
	}

	/**
	 * Fetch the latest "core update installed" report for this site as structured corroboration.
	 *
	 * @return  object|null  { success, failedStep, backupOnUpdate, httpCode, exceptionMessage, createdOn }
	 */
	protected function getLatestCoreUpdateReport(): ?object
	{
		try
		{
			/** @var \Akeeba\Panopticon\Model\Reports $reportModel */
			$reportModel = $this->getContainer()->mvcFactory->makeTempModel('Reports');
			$report      = $reportModel->findLatestRelevantEntry(
				(int) $this->id,
				\Akeeba\Panopticon\Library\Enumerations\ReportAction::CORE_UPDATE_INSTALLED,
				[]
			);
		}
		catch (Throwable)
		{
			return null;
		}

		if ($report === null)
		{
			return null;
		}

		$context    = $report->context;
		$subContext = $context->get('context', null);
		$httpCode   = null;

		// Best-effort extraction of an HTTP code from the recorded failure message.
		$exceptionMessage = null;

		if (is_object($subContext))
		{
			$exceptionMessage = $subContext->message ?? ($subContext->error ?? null);
		}
		elseif (is_array($subContext))
		{
			$exceptionMessage = $subContext['message'] ?? ($subContext['error'] ?? null);
		}

		if (!empty($exceptionMessage) && preg_match('/HTTP\s+(\d{3})/i', (string) $exceptionMessage, $m))
		{
			$httpCode = (int) $m[1];
		}

		return (object) [
			'success'          => $context->get('success', null),
			'failedStep'       => $context->get('failed_step', null),
			'backupOnUpdate'   => (bool) $context->get('backup_on_update', false),
			'httpCode'         => $httpCode,
			'exceptionMessage' => $exceptionMessage,
			'createdOn'        => $report->created_on ?? null,
		];
	}
}
