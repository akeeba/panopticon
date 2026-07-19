<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Security;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Awf\Utils\Ip;

/**
 * Matches host names and IP addresses against an operator-defined list of forbidden IP ranges.
 *
 * This implements the operator-declared destination policy which prevents a site definition URL from pointing into
 * network space the operator has declared off-limits, e.g. the internal network of a multi-tenant installation.
 *
 * The policy is **disabled by default**: an empty range list means every destination is allowed, which is the correct
 * behaviour for the single-tenant, self-hosted installations which form the majority of deployments. Only the operator
 * knows their own network topology, therefore only the operator can meaningfully declare what is "internal".
 *
 * Each range expression may be a single IP address (192.168.1.1), an inclusive range (192.168.1.1-192.168.1.255), an
 * IP with a netmask (192.168.1.1/255.255.255.0), or a CIDR block (192.168.1.1/24). Both IPv4 and IPv6 are supported.
 *
 * @since  1.4.0
 */
class ForbiddenIpRanges
{
	/**
	 * The forbidden IP range expressions.
	 *
	 * @var   array<string>
	 * @since 1.4.0
	 */
	private array $ranges;

	/**
	 * Constructor.
	 *
	 * @param   array<string>|string|null  $ranges  The forbidden IP range expressions.
	 *
	 * @since   1.4.0
	 */
	public function __construct(array|string|null $ranges = [])
	{
		$this->ranges = self::normaliseList($ranges);
	}

	/**
	 * Create an instance from the application configuration.
	 *
	 * @param   Container  $container  The application container.
	 *
	 * @return  self
	 * @since   1.4.0
	 */
	public static function fromConfig(Container $container): self
	{
		return new self($container->appConfig->get('forbidden_ip_ranges', []));
	}

	/**
	 * Normalise a raw configuration value into a clean list of range expressions.
	 *
	 * Accepts an array of rows (as posted by the repeatable form control), or a newline / comma separated string (as
	 * accepted by the CLI). Empty rows are discarded and duplicates are removed.
	 *
	 * @param   array<string>|string|null  $ranges  The raw value.
	 *
	 * @return  array<string>
	 * @since   1.4.0
	 */
	public static function normaliseList(array|string|null $ranges): array
	{
		if ($ranges === null)
		{
			return [];
		}

		if (is_string($ranges))
		{
			$ranges = preg_split('/[\r\n,]+/', $ranges) ?: [];
		}

		$ranges = array_map(fn($x) => trim((string) $x), $ranges);
		$ranges = array_filter($ranges, fn($x) => $x !== '');

		return array_values(array_unique($ranges));
	}

	/**
	 * Is the given string a syntactically valid IP range expression?
	 *
	 * This is a pure syntax check; it performs no network access. It is used to validate operator input in Global
	 * Configuration so that a typo cannot silently create a rule which never matches anything.
	 *
	 * @param   string  $expression  The expression to validate.
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	public static function isValidExpression(string $expression): bool
	{
		$expression = trim($expression);

		if ($expression === '')
		{
			return false;
		}

		// Inclusive range, e.g. 192.168.1.1-192.168.1.255
		if (str_contains($expression, '-'))
		{
			[$from, $to] = explode('-', $expression, 2);

			return self::isValidAddress(trim($from))
				&& self::isValidAddress(trim($to))
				&& (self::isIPv6(trim($from)) === self::isIPv6(trim($to)));
		}

		// CIDR block or netmask, e.g. 192.168.1.0/24 or 192.168.1.0/255.255.255.0
		if (str_contains($expression, '/'))
		{
			[$net, $mask] = explode('/', $expression, 2);
			$net  = trim($net);
			$mask = trim($mask);

			if (!self::isValidAddress($net) || $mask === '')
			{
				return false;
			}

			// Netmask notation, e.g. /255.255.255.0 — only meaningful for IPv4
			if (str_contains($mask, '.'))
			{
				return !self::isIPv6($net)
					&& filter_var($mask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
			}

			// Prefix length notation, e.g. /24
			if (!preg_match('/^\d+$/', $mask))
			{
				return false;
			}

			return ((int) $mask) <= (self::isIPv6($net) ? 128 : 32);
		}

		// Bare address
		return self::isValidAddress($expression);
	}

	/**
	 * Is the range list empty, i.e. is the policy disabled?
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	public function isEmpty(): bool
	{
		return empty($this->ranges);
	}

	/**
	 * Get the configured range expressions.
	 *
	 * @return  array<string>
	 * @since   1.4.0
	 */
	public function getRanges(): array
	{
		return $this->ranges;
	}

	/**
	 * Is this IP address inside any of the forbidden ranges?
	 *
	 * This is a pure check; it performs no network access.
	 *
	 * @param   string  $ip  The IP address to check.
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	public function isForbiddenIp(string $ip): bool
	{
		if ($this->isEmpty())
		{
			return false;
		}

		$ip = self::unwrapAddress($ip);

		if (!self::isValidAddress($ip))
		{
			return false;
		}

		return Ip::IPinList($ip, $this->ranges) === true;
	}

	/**
	 * Is this host forbidden?
	 *
	 * If the host is an IP address literal it is checked directly. Otherwise it is resolved through DNS and **every**
	 * resulting address is checked, so that a round-robin record mixing permitted and forbidden addresses cannot be
	 * used to slip through.
	 *
	 * A host which cannot be resolved is *not* considered forbidden. Refusing to save an unresolvable host would make
	 * the save path depend on DNS availability, so that a transient resolver failure would block legitimate edits. The
	 * connection-time enforcement catches such a host later, if and when it does resolve.
	 *
	 * @param   string  $host  The host name or IP address literal.
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	public function isForbiddenHost(string $host): bool
	{
		if ($this->isEmpty())
		{
			return false;
		}

		foreach ($this->resolveHost($host) as $ip)
		{
			if ($this->isForbiddenIp($ip))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve a host name to all of its IPv4 and IPv6 addresses.
	 *
	 * An IP address literal resolves to itself without any network access.
	 *
	 * @param   string  $host  The host name or IP address literal.
	 *
	 * @return  array<string>  The resolved addresses; empty if the host cannot be resolved.
	 * @since   1.4.0
	 */
	public function resolveHost(string $host): array
	{
		$host = self::unwrapAddress(trim($host));

		if ($host === '')
		{
			return [];
		}

		// An IP address literal resolves to itself.
		if (self::isValidAddress($host))
		{
			return [$host];
		}

		$addresses = [];

		// IPv4 (A records)
		$ipv4 = @gethostbynamel($host);

		if (is_array($ipv4))
		{
			$addresses = array_merge($addresses, $ipv4);
		}

		// IPv6 (AAAA records)
		if (function_exists('dns_get_record'))
		{
			$records = @dns_get_record($host, DNS_AAAA);

			if (is_array($records))
			{
				foreach ($records as $record)
				{
					if (!empty($record['ipv6']))
					{
						$addresses[] = $record['ipv6'];
					}
				}
			}
		}

		return array_values(array_unique(array_filter($addresses)));
	}

	/**
	 * Strip the square brackets from a bracketed IPv6 literal, e.g. [::1] becomes ::1.
	 *
	 * @param   string  $address  The address.
	 *
	 * @return  string
	 * @since   1.4.0
	 */
	private static function unwrapAddress(string $address): string
	{
		$address = trim($address);

		if (str_starts_with($address, '[') && str_ends_with($address, ']'))
		{
			return substr($address, 1, -1);
		}

		return $address;
	}

	/**
	 * Is this a valid IPv4 or IPv6 address literal?
	 *
	 * @param   string  $address  The address to check.
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	private static function isValidAddress(string $address): bool
	{
		return filter_var($address, FILTER_VALIDATE_IP) !== false;
	}

	/**
	 * Is this an IPv6 address literal?
	 *
	 * @param   string  $address  The address to check.
	 *
	 * @return  bool
	 * @since   1.4.0
	 */
	private static function isIPv6(string $address): bool
	{
		return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
	}
}
