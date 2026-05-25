<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Api\ApiHandlerInterface;
use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Akeeba\Panopticon\Library\Api\TokenAuthentication;
use Awf\Mvc\Controller;

/**
 * API Dispatcher Controller
 *
 * Authenticates API requests via token, resolves the handler class, and dispatches.
 *
 * @since  1.4.0
 */
class Api extends Controller
{
	use ACLTrait;

	public function __construct($container = null)
	{
		parent::__construct($container);

		// All API requests dispatch through the same method, regardless of the requested task.
		$this->registerDefaultTask('dispatch');
	}

	/**
	 * Runs before executing any task.
	 *
	 * Authenticates the request using API token authentication.
	 *
	 * @return  bool  True to continue, false to abort.
	 * @since   1.4.0
	 */
	protected function onBeforeExecute(): bool
	{
		// Disable legacy hashes for JSON output
		$doc = $this->getContainer()->application->getDocument();

		if ($doc instanceof \Awf\Document\Json)
		{
			$doc->setUseHashes(false);
		}

		// Authenticate via API token
		$auth   = new TokenAuthentication($this->getContainer());
		$userId = $auth->authenticateRequest();

		if ($userId === null)
		{
			$this->sendJsonError(401, 'Invalid or missing API token.', 'auth.invalid_token');

			return false;
		}

		return true;
	}

	/**
	 * Dispatch to the appropriate API handler.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	public function dispatch(): void
	{
		$handlerSuffix = $this->input->getString('handler', '');

		if (empty($handlerSuffix))
		{
			$this->sendJsonError(404, 'Unknown API endpoint.', 'route.not_found');

			return;
		}

		$handlerClass = 'Akeeba\\Panopticon\\Controller\\Api\\' . $handlerSuffix;

		if (!class_exists($handlerClass) || !is_subclass_of($handlerClass, ApiHandlerInterface::class))
		{
			$this->sendJsonError(404, 'Unknown API endpoint.', 'route.not_found');

			return;
		}

		$handler = new $handlerClass($this->getContainer(), $this->input);
		$handler->handle();
	}

	/**
	 * Send a JSON error response and terminate.
	 *
	 * @param   int          $httpCode  HTTP status code.
	 * @param   string       $message   Human-readable error message.
	 * @param   string|null  $code      Stable machine-readable error code (e.g. "auth.invalid_token").
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	private function sendJsonError(int $httpCode, string $message, ?string $code = null): void
	{
		@ob_end_clean();
		http_response_code($httpCode);

		// On 401, advertise the supported authentication scheme per RFC 7235.
		if ($httpCode === 401)
		{
			header('WWW-Authenticate: Bearer realm="Panopticon API"');
		}

		header('Content-Type: application/json; charset=utf-8');

		$payload = [
			'success' => false,
		];

		if ($code !== null)
		{
			$payload['code'] = $code;
		}

		$payload['message'] = $message;

		echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$this->getContainer()->application->close();
	}
}
