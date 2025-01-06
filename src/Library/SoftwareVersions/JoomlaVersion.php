<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\SoftwareVersions;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Version\Version;
use DateInterval;
use DateTime;
use GuzzleHttp\ClientInterface;

class JoomlaVersion
{
	private DateTime $expiration;

	public function __construct(private ?Container $container = null, private ?ClientInterface $httpClient = null)
	{
		$this->container ??= Factory::getContainer();

		if (empty($this->httpClient))
		{
			$today            = $this->container->dateFactory();
			$this->expiration = (clone $today)->add(new DateInterval('P1W'));
			$interval         = $today->diff($this->expiration);

			$this->httpClient = $this->container->httpFactory->makeClient(
				cacheTTL: $interval->days * 86400
			);
		}
	}

	/**
	 * Get the Joomla! release dates and End-of-Life information per version family.
	 *
	 * I am hard–coding this information. It is based on the sources below.
	 *
	 * At the time this code was written only Joomla! versions up to 5.0 and 4.4 were released. The other dates are
	 * approximations based on the published information about the _intended_ release cycle. If this changes, we will
	 * update this information.
	 *
	 * At the time this code was written there is absolutely no information about the active support of x.4 releases,
	 * beyond the fact that they become EOL 2 years after their release (4 years since the release of the x.0 release
	 * in the major version series). Since Joomla 3.10 which had the same role as a “caretaker version”, as they weirdly
	 * put it in their release announcements, received bug fixes for a year and security updates for another year I am
	 * using the same approach in my approximation of support dates.
	 *
	 * @see    https://docs.joomla.org/Joomla!_CMS_versions
	 * @see    https://downloads.joomla.org
	 * @see    https://www.joomla.org/announcements/release-news/5868
	 * @see    https://www.joomla.org/announcements/release-news/5900
	 *
	 * @return  array
	 * @throws  \Psr\Cache\CacheException
	 * @throws  \Psr\Cache\InvalidArgumentException
	 *
	 * @since  1.0.1
	 */
	public function getJoomlaEolInformation(): array
	{
		return [
			// Joomla! 1.0
			'1.0'  => (object) [
				'cycle'         => '1.0',
				'firstRelease'  => new DateTime('2005-09-17 00:30'),
				'activeSupport' => new DateTime('2009-07-22 00:00'),
				'eolBranch'     => new DateTime('2009-07-22 00:00'),
				'eolMajor'      => new DateTime('2009-07-22 00:00'),
			],

			// Joomla! 1.5
			'1.5'  => (object) [
				'cycle'         => '1.5',
				'firstRelease'  => new DateTime('2008-01-21 23:55'),
				'activeSupport' => new DateTime('2012-09-27 18:00'),
				'eolBranch'     => new DateTime('2012-09-27 18:00'),
				'eolMajor'      => new DateTime('2012-09-27 18:00'),
			],

			// Joomla! 2 (1.6, 1.7, and 2.5 - because versioning was on crystal meth at the time…)
			'1.6'  => (object) [
				'cycle'         => '2.5',
				'firstRelease'  => new DateTime('2011-01-10 23:00'),
				'activeSupport' => new DateTime('2011-08-10 18:00'),
				'eolBranch'     => new DateTime('2011-08-10 18:00'),
				'eolMajor'      => new DateTime('2011-08-10 18:00'),
			],
			'1.7'  => (object) [
				'cycle'         => '2.5',
				'firstRelease'  => new DateTime('2011-07-19 14:00'),
				'activeSupport' => new DateTime('2012-02-19 18:00'),
				'eolBranch'     => new DateTime('2012-02-19 18:00'),
				'eolMajor'      => new DateTime('2012-02-19 18:00'),
			],
			'2.5'  => (object) [
				'cycle'         => '2.5',
				'firstRelease'  => new DateTime('2012-01-24 14:00'),
				'activeSupport' => new DateTime('2014-12-31 23:59:59'),
				'eolBranch'     => new DateTime('2014-12-31 23:59:59'),
				'eolMajor'      => new DateTime('2014-12-31 23:59:59'),
			],

			// Joomla! 3.x
			'3.0'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2012-09-27 14:00'),
				'activeSupport' => new DateTime('2013-05-27 00:00'),
				'eolBranch'     => new DateTime('2013-05-27 00:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.1'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2013-04-23 14:00'),
				'activeSupport' => new DateTime('2013-12-23 00:00'),
				'eolBranch'     => new DateTime('2013-12-23 00:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.2'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2013-11-06 14:00'),
				'activeSupport' => new DateTime('2014-10-06 00:00'),
				'eolBranch'     => new DateTime('2014-10-06 00:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.3'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2014-04-30 14:00'),
				'activeSupport' => new DateTime('2015-02-24 20:00'),
				'eolBranch'     => new DateTime('2015-02-24 20:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.4'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2015-02-24 20:00'),
				'activeSupport' => new DateTime('2016-03-21 22:00'),
				'eolBranch'     => new DateTime('2016-03-21 22:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.5'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2016-03-21 22:00'),
				'activeSupport' => new DateTime('2016-07-12 21:00'),
				'eolBranch'     => new DateTime('2016-07-12 21:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.6'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2016-07-12 21:00'),
				'activeSupport' => new DateTime('2017-04-25 17:00'),
				'eolBranch'     => new DateTime('2017-04-25 17:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.7'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2017-04-25 17:00'),
				'activeSupport' => new DateTime('2017-09-18 00:00'),
				'eolBranch'     => new DateTime('2017-09-18 00:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.8'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2017-09-18 00:00'),
				'activeSupport' => new DateTime('2018-10-30 14:00'),
				'eolBranch'     => new DateTime('2018-10-30 14:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.9'  => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2018-10-30 14:00'),
				'activeSupport' => new DateTime('2021-08-17 00:00'),
				'eolBranch'     => new DateTime('2021-08-17 00:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],
			'3.10' => (object) [
				'cycle'         => '3.x',
				'firstRelease'  => new DateTime('2021-08-17 00:00'),
				'activeSupport' => new DateTime('2022-08-17 00:00'),
				'eolBranch'     => new DateTime('2023-08-17 00:00'),
				'eolMajor'      => new DateTime('2023-08-17 00:00'),
			],

			// Joomla! 4.x
			'4.0'  => (object) [
				'cycle'         => '4.x',
				'firstRelease'  => new DateTime('2021-08-17 00:00'),
				'activeSupport' => new DateTime('2022-02-15 00:00'),
				'eolBranch'     => new DateTime('2022-02-15 00:00'),
				'eolMajor'      => new DateTime('2025-10-17 16:00'),
			],
			'4.1'  => (object) [
				'cycle'         => '4.x',
				'firstRelease'  => new DateTime('2022-02-15 00:00'),
				'activeSupport' => new DateTime('2022-08-16 16:00'),
				'eolBranch'     => new DateTime('2022-08-16 16:00'),
				'eolMajor'      => new DateTime('2025-10-17 16:00'),
			],
			'4.2'  => (object) [
				'cycle'         => '4.x',
				'firstRelease'  => new DateTime('2022-08-16 16:00'),
				'activeSupport' => new DateTime('2023-04-18 16:00'),
				'eolBranch'     => new DateTime('2023-04-18 16:00'),
				'eolMajor'      => new DateTime('2025-10-17 16:00'),
			],
			'4.3'  => (object) [
				'cycle'         => '4.x',
				'firstRelease'  => new DateTime('2023-04-18 16:00'),
				'activeSupport' => new DateTime('2023-04-18 16:00'),
				'eolBranch'     => new DateTime('2023-04-18 16:00'),
				'eolMajor'      => new DateTime('2025-10-17 16:00'),
			],
			'4.4'  => (object) [
				'cycle'         => '4.x',
				'firstRelease'  => new DateTime('2023-10-17 16:00'),
				'activeSupport' => new DateTime('2024-10-17 16:00'),
				'eolBranch'     => new DateTime('2025-10-17 16:00'),
				'eolMajor'      => new DateTime('2025-10-17 16:00'),
			],

			// Joomla! 5.x
			'5.0'  => (object) [
				'cycle'         => '5.x',
				'firstRelease'  => new DateTime('2023-10-17 16:00'),
				'activeSupport' => new DateTime('2024-04-17 16:00'),
				'eolBranch'     => new DateTime('2024-04-17 16:00'),
				'eolMajor'      => new DateTime('2027-10-17 16:00'),
			],
			'5.1'  => (object) [
				'cycle'         => '5.x',
				'firstRelease'  => new DateTime('2024-04-17 16:00'),
				'activeSupport' => new DateTime('2024-10-17 16:00'),
				'eolBranch'     => new DateTime('2024-10-17 16:00'),
				'eolMajor'      => new DateTime('2027-10-17 16:00'),
			],
			'5.2'  => (object) [
				'cycle'         => '5.x',
				'firstRelease'  => new DateTime('2024-10-17 16:00'),
				'activeSupport' => new DateTime('2025-04-17 16:00'),
				'eolBranch'     => new DateTime('2025-04-17 16:00'),
				'eolMajor'      => new DateTime('2027-10-17 16:00'),
			],
			'5.3'  => (object) [
				'cycle'         => '5.x',
				'firstRelease'  => new DateTime('2025-04-17 16:00'),
				'activeSupport' => new DateTime('2025-10-17 16:00'),
				'eolBranch'     => new DateTime('2025-10-17 16:00'),
				'eolMajor'      => new DateTime('2027-10-17 16:00'),
			],
			'5.4'  => (object) [
				'cycle'         => '5.x',
				'firstRelease'  => new DateTime('2025-10-17 16:00'),
				'activeSupport' => new DateTime('2026-10-17 16:00'),
				'eolBranch'     => new DateTime('2027-10-17 16:00'),
				'eolMajor'      => new DateTime('2027-10-17 16:00'),
			],

			// Joomla! 6.x
			'6.0'  => (object) [
				'cycle'         => '6.x',
				'firstRelease'  => new DateTime('2025-10-17 16:00'),
				'activeSupport' => new DateTime('2026-04-17 16:00'),
				'eolBranch'     => new DateTime('2026-04-17 16:00'),
				'eolMajor'      => new DateTime('2029-10-17 16:00'),
			],
			'6.1'  => (object) [
				'cycle'         => '6.x',
				'firstRelease'  => new DateTime('2026-04-17 16:00'),
				'activeSupport' => new DateTime('2026-10-17 16:00'),
				'eolBranch'     => new DateTime('2026-10-17 16:00'),
				'eolMajor'      => new DateTime('2029-10-17 16:00'),
			],
			'6.2'  => (object) [
				'cycle'         => '6.x',
				'firstRelease'  => new DateTime('2026-10-17 16:00'),
				'activeSupport' => new DateTime('2027-04-17 16:00'),
				'eolBranch'     => new DateTime('2027-04-17 16:00'),
				'eolMajor'      => new DateTime('2029-10-17 16:00'),
			],
			'6.3'  => (object) [
				'cycle'         => '6.x',
				'firstRelease'  => new DateTime('2027-04-17 16:00'),
				'activeSupport' => new DateTime('2027-10-17 16:00'),
				'eolBranch'     => new DateTime('2027-10-17 16:00'),
				'eolMajor'      => new DateTime('2029-10-17 16:00'),
			],
			'6.4'  => (object) [
				'cycle'         => '6.x',
				'firstRelease'  => new DateTime('2027-10-17 16:00'),
				'activeSupport' => new DateTime('2028-10-17 16:00'),
				'eolBranch'     => new DateTime('2029-10-17 16:00'),
				'eolMajor'      => new DateTime('2029-10-17 16:00'),
			],

			// Joomla! 7.x
			'7.0'  => (object) [
				'cycle'         => '7.x',
				'firstRelease'  => new DateTime('2027-10-17 16:00'),
				'activeSupport' => new DateTime('2028-04-17 16:00'),
				'eolBranch'     => new DateTime('2028-04-17 16:00'),
				'eolMajor'      => new DateTime('2031-10-17 16:00'),
			],
			'7.1'  => (object) [
				'cycle'         => '7.x',
				'firstRelease'  => new DateTime('2028-04-17 16:00'),
				'activeSupport' => new DateTime('2028-10-17 16:00'),
				'eolBranch'     => new DateTime('2028-10-17 16:00'),
				'eolMajor'      => new DateTime('2031-10-17 16:00'),
			],
			'7.2'  => (object) [
				'cycle'         => '7.x',
				'firstRelease'  => new DateTime('2028-10-17 16:00'),
				'activeSupport' => new DateTime('2029-04-17 16:00'),
				'eolBranch'     => new DateTime('2029-04-17 16:00'),
				'eolMajor'      => new DateTime('2031-10-17 16:00'),
			],
			'7.3'  => (object) [
				'cycle'         => '7.x',
				'firstRelease'  => new DateTime('2029-04-17 16:00'),
				'activeSupport' => new DateTime('2029-10-17 16:00'),
				'eolBranch'     => new DateTime('2029-10-17 16:00'),
				'eolMajor'      => new DateTime('2031-10-17 16:00'),
			],
			'7.4'  => (object) [
				'cycle'         => '7.x',
				'firstRelease'  => new DateTime('2029-10-17 16:00'),
				'activeSupport' => new DateTime('2030-10-17 16:00'),
				'eolBranch'     => new DateTime('2031-10-17 16:00'),
				'eolMajor'      => new DateTime('2031-10-17 16:00'),
			],

			// Joomla! 8.x
			'8.0'  => (object) [
				'cycle'         => '8.x',
				'firstRelease'  => new DateTime('2029-10-17 16:00'),
				'activeSupport' => new DateTime('2030-04-17 16:00'),
				'eolBranch'     => new DateTime('2030-04-17 16:00'),
				'eolMajor'      => new DateTime('2033-10-17 16:00'),
			],
			'8.1'  => (object) [
				'cycle'         => '8.x',
				'firstRelease'  => new DateTime('2030-04-17 16:00'),
				'activeSupport' => new DateTime('2030-10-17 16:00'),
				'eolBranch'     => new DateTime('2030-10-17 16:00'),
				'eolMajor'      => new DateTime('2033-10-17 16:00'),
			],
			'8.2'  => (object) [
				'cycle'         => '8.x',
				'firstRelease'  => new DateTime('2030-10-17 16:00'),
				'activeSupport' => new DateTime('2031-04-17 16:00'),
				'eolBranch'     => new DateTime('2031-04-17 16:00'),
				'eolMajor'      => new DateTime('2033-10-17 16:00'),
			],
			'8.3'  => (object) [
				'cycle'         => '8.x',
				'firstRelease'  => new DateTime('2031-04-17 16:00'),
				'activeSupport' => new DateTime('2031-10-17 16:00'),
				'eolBranch'     => new DateTime('2031-10-17 16:00'),
				'eolMajor'      => new DateTime('2033-10-17 16:00'),
			],
			'8.4'  => (object) [
				'cycle'         => '8.x',
				'firstRelease'  => new DateTime('2031-10-17 16:00'),
				'activeSupport' => new DateTime('2032-10-17 16:00'),
				'eolBranch'     => new DateTime('2033-10-17 16:00'),
				'eolMajor'      => new DateTime('2033-10-17 16:00'),
			],
		];
	}

	/**
	 * Get support status information for a Joomla! version
	 *
	 * @param   string         $version  The version to check, e.g. 4.4.0
	 * @param   DateTime|null  $today    The date against to check. NULL to use the current date and time
	 *
	 * @return  object
	 * @throws  \Psr\Cache\CacheException
	 * @throws  \Psr\Cache\InvalidArgumentException
	 */
	public function getVersionInformation(string $version, ?DateTime $today = null): object
	{
		// Initialisation
		$joomlaInfo    = $this->getJoomlaEolInformation();
		$versionObject = Version::create($version);

		$ret = (object) [
			'series'    => null,
			'unknown'   => true,
			'supported' => false,
			'security'  => false,
			'eolBranch' => false,
			'eol'       => false,
			'dates'     => (object) [
				'firstRelease'  => null,
				'activeSupport' => null,
				'eol'           => null,
				'eolBranch'     => null,
			],
		];

		$series     = $versionObject->versionFamily();
		$familyInfo = $joomlaInfo[$series] ?? null;

		if (empty($familyInfo))
		{
			return $ret;
		}

		$ret->series               = $familyInfo->cycle;
		$ret->unknown              = false;
		$ret->dates->firstRelease  = $familyInfo->firstRelease;
		$ret->dates->activeSupport = $familyInfo->activeSupport;
		$ret->dates->eolBranch     = $familyInfo->eolBranch;
		$ret->dates->eol           = $familyInfo->eolMajor;

		$today          ??= new DateTime();
		$ret->eol       = $ret->dates->eol->diff($today)->invert === 0;
		$ret->eolBranch = $ret->dates->eolBranch->diff($today)->invert === 0;
		$ret->supported = !$ret->eol && !$ret->eolBranch
		                  && $ret->dates->activeSupport->diff($today)->invert === 1;
		$ret->security  = !$ret->supported && !$ret->eolBranch && !$ret->eol;

		return $ret;
	}

	public function isEOLBranch(string $version): bool
	{
		$versionInformation = $this->getVersionInformation($version);

		return $versionInformation->eol || $versionInformation->eolBranch;
	}

	public function isEOLMajor(string $version): bool
	{
		$versionInformation = $this->getVersionInformation($version);

		return $versionInformation->eol;
	}

	public function isSecurity(string $version): bool
	{
		$versionInformation = $this->getVersionInformation($version);

		return !$versionInformation->security;
	}
}