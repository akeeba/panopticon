<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Session;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Container\Container;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Encrypt\Aes;
use Awf\Session\Encoder\EncoderInterface;

/**
 * A session encoder/decoder which encrypts the stored session data with the installation's secret.
 *
 * If $allowTransparent is enabled it will fall back to raw, unencrypted data.
 *
 * @since  1.2.0
 */
class EncryptingEncoder implements EncoderInterface, ContainerAwareInterface
{
	use ContainerAwareTrait;

	private Aes $aes;

	private bool $allowEncryption;

	public function __construct(
		?Container $container = null,
		private $allowTransparent = true
	)
	{
		$this->setContainer($container ?? Factory::getContainer());

		$this->aes = new Aes(
			$this->getContainer()->appConfig->get('secret')
		);

		$this->allowEncryption = Aes::isSupported()
			&& $this->getContainer()->appConfig->get('session_encrypt', true);
	}

	public function isAvailable(): bool
	{
		return $this->allowTransparent || Aes::isSupported();
	}

	public function encode(?array $raw)
	{
		// If encryption is disabled, fall back to raw encoding
		if (!$this->allowEncryption && $this->allowTransparent)
		{
			return $raw;
		}

		return '###AES128###' . $this->aes->encryptString(serialize($raw)) ?? '';
	}

	public function decode($encoded): array
	{
		if (
			$this->allowTransparent &&
			(!is_string($encoded) || !str_starts_with($encoded, '###AES128###'))
		)
		{
			return is_array($encoded) ? $encoded : [];
		}

		if (!is_string($encoded) || !str_starts_with($encoded, '###AES128###'))
		{
			return [];
		}

		if (empty($encoded) || strlen($encoded) <= 12)
		{
			return [];
		}

		$decoded = $this->aes->decryptString(substr($encoded, 12));

		if (empty($decoded))
		{
			return [];
		}

		try
		{
			$ret = @unserialize($decoded) ?? [];
		}
		catch (\Exception)
		{
			$ret = [];
		}

		if (empty($ret))
		{
			return [];
		}

		return $ret;
	}
}