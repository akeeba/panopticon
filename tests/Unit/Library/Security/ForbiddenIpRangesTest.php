<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Library\Security;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Security\ForbiddenIpRanges;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pure-unit tests for {@see ForbiddenIpRanges}.
 *
 * These tests never touch the network. Host resolution is covered by the integration suite.
 *
 * @since  1.4.0
 */
class ForbiddenIpRangesTest extends AbstractUnitTestCase
{
	public function testEmptyByDefault(): void
	{
		$ranges = new ForbiddenIpRanges();

		$this->assertTrue($ranges->isEmpty());
		$this->assertSame([], $ranges->getRanges());
	}

	/**
	 * The policy is disabled by default: with no ranges configured nothing is forbidden.
	 */
	public function testEmptyListForbidsNothing(): void
	{
		$ranges = new ForbiddenIpRanges([]);

		$this->assertFalse($ranges->isForbiddenIp('127.0.0.1'));
		$this->assertFalse($ranges->isForbiddenIp('169.254.169.254'));
		$this->assertFalse($ranges->isForbiddenIp('::1'));
	}

	public function testNormaliseListFromArray(): void
	{
		$ranges = new ForbiddenIpRanges(['  10.0.0.1 ', '', '10.0.0.1', '192.168.1.0/24']);

		$this->assertSame(['10.0.0.1', '192.168.1.0/24'], $ranges->getRanges());
	}

	public function testNormaliseListFromString(): void
	{
		$ranges = new ForbiddenIpRanges("10.0.0.1\n192.168.1.0/24,  172.16.0.0/12 \n\n");

		$this->assertSame(['10.0.0.1', '192.168.1.0/24', '172.16.0.0/12'], $ranges->getRanges());
	}

	public function testNormaliseListFromNull(): void
	{
		$this->assertSame([], (new ForbiddenIpRanges(null))->getRanges());
	}

	#[DataProvider('validExpressionProvider')]
	public function testValidExpressions(string $expression): void
	{
		$this->assertTrue(
			ForbiddenIpRanges::isValidExpression($expression),
			sprintf('Expression "%s" should be valid', $expression)
		);
	}

	public static function validExpressionProvider(): array
	{
		return [
			'single IPv4'      => ['192.168.1.1'],
			'single IPv6'      => ['::1'],
			'full IPv6'        => ['fd00:ec2::254'],
			'IPv4 range'       => ['192.168.1.1-192.168.1.255'],
			'IPv6 range'       => ['fd00::1-fd00::ffff'],
			'IPv4 CIDR'        => ['192.168.1.0/24'],
			'IPv4 CIDR zero'   => ['0.0.0.0/0'],
			'IPv4 CIDR 32'     => ['192.168.1.1/32'],
			'IPv6 CIDR'        => ['fd00::/8'],
			'IPv6 CIDR 128'    => ['::1/128'],
			'IPv4 netmask'     => ['192.168.1.1/255.255.255.0'],
			'whitespace'       => ['  10.0.0.1  '],
		];
	}

	#[DataProvider('invalidExpressionProvider')]
	public function testInvalidExpressions(string $expression): void
	{
		$this->assertFalse(
			ForbiddenIpRanges::isValidExpression($expression),
			sprintf('Expression "%s" should be invalid', $expression)
		);
	}

	public static function invalidExpressionProvider(): array
	{
		return [
			'empty'                 => [''],
			'whitespace only'       => ['   '],
			'host name'             => ['example.com'],
			'garbage'               => ['not an ip'],
			'octet out of range'    => ['192.168.1.256'],
			'truncated'             => ['192.168.1'],
			'IPv4 prefix too big'   => ['192.168.1.0/33'],
			'IPv6 prefix too big'   => ['fd00::/129'],
			'non numeric prefix'    => ['192.168.1.0/abc'],
			'empty prefix'          => ['192.168.1.0/'],
			'bad netmask'           => ['192.168.1.0/255.255.255.999'],
			'netmask on IPv6'       => ['fd00::/255.255.255.0'],
			'range mixing families' => ['192.168.1.1-fd00::1'],
			'range bad endpoint'    => ['192.168.1.1-nonsense'],
			'CIDR bad network'      => ['nonsense/24'],
		];
	}

	#[DataProvider('matchProvider')]
	public function testMatching(array $configuredRanges, string $ip, bool $expected): void
	{
		$ranges = new ForbiddenIpRanges($configuredRanges);

		$this->assertSame(
			$expected,
			$ranges->isForbiddenIp($ip),
			sprintf('IP %s against %s', $ip, implode(', ', $configuredRanges))
		);
	}

	public static function matchProvider(): array
	{
		return [
			'exact IPv4 hit'          => [['127.0.0.1'], '127.0.0.1', true],
			'exact IPv4 miss'         => [['127.0.0.1'], '127.0.0.2', false],
			'CIDR hit'                => [['192.168.1.0/24'], '192.168.1.77', true],
			'CIDR miss'               => [['192.168.1.0/24'], '192.168.2.77', false],
			'CIDR /8 hit'             => [['10.0.0.0/8'], '10.11.12.13', true],
			'CIDR /8 miss'            => [['10.0.0.0/8'], '11.0.0.1', false],
			'RFC1918 172 hit'         => [['172.16.0.0/12'], '172.20.1.1', true],
			'RFC1918 172 miss'        => [['172.16.0.0/12'], '172.32.1.1', false],
			'range hit lower bound'   => [['192.168.1.10-192.168.1.20'], '192.168.1.10', true],
			'range hit upper bound'   => [['192.168.1.10-192.168.1.20'], '192.168.1.20', true],
			'range hit middle'        => [['192.168.1.10-192.168.1.20'], '192.168.1.15', true],
			'range miss below'        => [['192.168.1.10-192.168.1.20'], '192.168.1.9', false],
			'range miss above'        => [['192.168.1.10-192.168.1.20'], '192.168.1.21', false],
			'netmask hit'             => [['192.168.1.0/255.255.255.0'], '192.168.1.5', true],
			'netmask miss'            => [['192.168.1.0/255.255.255.0'], '192.168.5.1', false],
			'metadata endpoint'       => [['169.254.169.254'], '169.254.169.254', true],
			'link local block'        => [['169.254.0.0/16'], '169.254.169.254', true],
			'IPv6 loopback hit'       => [['::1'], '::1', true],
			'IPv6 bracketed literal'  => [['::1'], '[::1]', true],
			'IPv6 CIDR hit'           => [['fd00::/8'], 'fd00::1234', true],
			'IPv6 CIDR miss'          => [['fd00::/8'], '2001:db8::1', false],
			'IPv6 metadata endpoint'  => [['fd00:ec2::254'], 'fd00:ec2::254', true],
			'multiple ranges, second' => [['10.0.0.0/8', '192.168.0.0/16'], '192.168.1.1', true],
			'multiple ranges, none'   => [['10.0.0.0/8', '192.168.0.0/16'], '8.8.8.8', false],
			'IPv4 not matched by v6'  => [['fd00::/8'], '192.168.1.1', false],
			'IPv6 not matched by v4'  => [['192.168.0.0/16'], 'fd00::1', false],
			'invalid input is safe'   => [['10.0.0.0/8'], 'not-an-ip', false],
			'empty input is safe'     => [['10.0.0.0/8'], '', false],
		];
	}

	/**
	 * An IP address literal must resolve to itself without any network access.
	 */
	public function testResolveHostWithIpLiteral(): void
	{
		$ranges = new ForbiddenIpRanges(['10.0.0.0/8']);

		$this->assertSame(['192.168.1.1'], $ranges->resolveHost('192.168.1.1'));
		$this->assertSame(['::1'], $ranges->resolveHost('::1'));
		$this->assertSame(['::1'], $ranges->resolveHost('[::1]'));
	}

	public function testResolveHostWithEmptyInput(): void
	{
		$this->assertSame([], (new ForbiddenIpRanges(['10.0.0.0/8']))->resolveHost('   '));
	}

	/**
	 * An IP literal host is checked directly, with no DNS lookup involved.
	 */
	public function testIsForbiddenHostWithIpLiteral(): void
	{
		$ranges = new ForbiddenIpRanges(['10.0.0.0/8', '::1']);

		$this->assertTrue($ranges->isForbiddenHost('10.1.2.3'));
		$this->assertTrue($ranges->isForbiddenHost('[::1]'));
		$this->assertFalse($ranges->isForbiddenHost('8.8.8.8'));
	}

	/**
	 * With the policy disabled no host is forbidden, and no DNS lookup is attempted.
	 */
	public function testIsForbiddenHostShortCircuitsWhenDisabled(): void
	{
		$ranges = new ForbiddenIpRanges([]);

		$this->assertFalse($ranges->isForbiddenHost('this-host-does-not-exist.invalid'));
	}

	/**
	 * An unresolvable host is not forbidden: validation fails open so that a transient DNS failure cannot block
	 * legitimate edits. Connection-time enforcement catches it later.
	 */
	public function testUnresolvableHostFailsOpen(): void
	{
		$ranges = new ForbiddenIpRanges(['10.0.0.0/8']);

		$this->assertFalse($ranges->isForbiddenHost('this-host-does-not-exist.invalid'));
	}
}
