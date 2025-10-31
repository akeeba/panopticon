<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Password;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Container\Container;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Utility class to check if a password is reported as leaked in the “Have I Been Pwned?” database.
 *
 * @since 1.2.0
 */
class HIBPCheck implements ContainerAwareInterface
{
	use ContainerAwareTrait;

	private const API_URL_MASK = 'https://api.pwnedpasswords.com/range/%s';

	/**
	 * Public constructor.
	 *
	 * @param   Container|null        $container   The container object that holds the dependencies. Defaults to null
	 *                                             which uses the application container.
	 * @param   ClientInterface|null  $httpClient  The HTTP client object to make requests. Defaults to null which
	 *                                             initialises a new client object.
	 *
	 * @since   1.2.0
	 */
	public function __construct(?Container $container = null, private ?ClientInterface $httpClient = null)
	{
		$this->setContainer($container ?? Factory::getContainer());

		$this->httpClient ??= $this->container->httpFactory->makeClient(
			cacheTTL: 3600
		);
	}

	/**
	 * Checks if a password has been leaked.
	 *
	 * @param   string  $password  The password to check.
	 *
	 * @return  bool    Returns true if the password has been leaked, false otherwise.
	 *
	 * @since   1.2.0
	 * @see     https://haveibeenpwned.com/API/v2#PwnedPasswords
	 */
	public function isPasswordLeaked(string $password): bool
	{
		$sum     = strtoupper(hash('sha1', $password));
		$key     = substr($sum, 0, 5);
		$url     = sprintf(self::API_URL_MASK, $key);
		$options = $this->container->httpFactory->getDefaultRequestOptions();

		try
		{
			$response = $this->httpClient->get($url, $options);
		}
		catch (GuzzleException)
		{
			return false;
		}

		if ($response->getStatusCode() != 200)
		{
			return false;
		}

		$text = $response?->getBody()?->getContents() ?: '';

		if (empty($text))
		{
			return false;
		}

		$knownSums = array_map(
			fn($x) => strtoupper($key . explode(':', $x)[0]),
			explode("\n", (string) $text)
		);

		return in_array($sum, $knownSums);
	}
}