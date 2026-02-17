<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller\Api;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Site;
use Awf\Input\Input;

/**
 * Abstract base class for API handler classes.
 *
 * Provides shared helpers for JSON responses, input parsing, ACL, etc.
 *
 * @since  1.4.0
 */
abstract class AbstractApiHandler implements ApiHandlerInterface
{
	public function __construct(
		protected readonly Container $container,
		protected readonly Input $input
	) {}

	/**
	 * Send a successful JSON response and terminate.
	 *
	 * @param   mixed       $data        The response data.
	 * @param   int         $httpCode    HTTP status code.
	 * @param   string|null $message     Optional message.
	 * @param   array|null  $pagination  Optional pagination info.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	protected function sendJsonResponse(
		mixed $data,
		int $httpCode = 200,
		?string $message = null,
		?array $pagination = null
	): void
	{
		$response = [
			'success' => true,
			'data'    => $data,
		];

		if ($message !== null)
		{
			$response['message'] = $message;
		}

		if ($pagination !== null)
		{
			$response['pagination'] = $pagination;
		}

		@ob_end_clean();
		http_response_code($httpCode);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$this->container->application->close();
	}

	/**
	 * Send a JSON error response and terminate.
	 *
	 * @param   int     $httpCode  HTTP status code.
	 * @param   string  $message   Error message.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	protected function sendJsonError(int $httpCode, string $message): void
	{
		@ob_end_clean();
		http_response_code($httpCode);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(
			[
				'success' => false,
				'message' => $message,
			],
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$this->container->application->close();
	}

	/**
	 * Require the current user to be a super user.
	 *
	 * Sends a 403 error and terminates if the user is not a super user.
	 *
	 * @return  void
	 * @since   1.4.0
	 */
	protected function requireSuperUser(): void
	{
		$user = $this->container->userManager->getUser();

		if (!$user->getPrivilege('panopticon.super'))
		{
			$this->sendJsonError(403, 'This endpoint requires super user privileges.');
		}
	}

	/**
	 * Parse the JSON request body.
	 *
	 * @return  array  The decoded JSON body as an associative array.
	 * @since   1.4.0
	 */
	protected function getJsonBody(): array
	{
		$raw = file_get_contents('php://input');

		if (empty($raw))
		{
			return [];
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded))
		{
			$this->sendJsonError(400, 'Invalid JSON in request body.');
		}

		return $decoded;
	}

	/**
	 * Load a site by ID, checking per-site ACL privileges.
	 *
	 * @param   int     $id         The site ID.
	 * @param   string  $privilege  The required privilege (e.g. 'read', 'run', 'admin').
	 *
	 * @return  Site  The loaded site model.
	 * @since   1.4.0
	 */
	protected function getSiteWithPermission(int $id, string $privilege): Site
	{
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');

		try
		{
			$site->findOrFail($id);
		}
		catch (\Exception $e)
		{
			$this->sendJsonError(404, 'Site not found.');
		}

		$user = $this->container->userManager->getUser();

		if (!$user->getPrivilege('panopticon.super'))
		{
			if (!$user->authorise('panopticon.' . $privilege, $id))
			{
				$this->sendJsonError(403, 'You do not have permission to access this site.');
			}
		}

		return $site;
	}
}
