<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Passkey;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Database\Driver;
use Exception;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class CredentialRepository
{
	/**
	 * Returns a CredentialRecord object given the public key credential ID
	 *
	 * @param   string  $publicKeyCredentialId  The identified of the public key credential we're searching for
	 *
	 * @return  CredentialRecord|null
	 * @since   1.2.3
	 */
	public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
	{
		$db           = $this->getDatabase();
		$credentialId = base64_encode($publicKeyCredentialId);
		$query        = $db->getQuery(true)
			->select($db->quoteName('credential'))
			->from($db->quoteName('#__passkeys'))
			->where($db->quoteName('id') . ' = ' . $db->quote($credentialId));

		$json = $db->setQuery($query)->loadResult();

		if (empty($json))
		{
			return null;
		}

		try
		{
			return $this->getCredentialSerializer()->deserialize((string) $json, CredentialRecord::class, 'json');
		}
		catch (Throwable)
		{
			return null;
		}
	}

	/**
	 * Returns all CredentialRecord objects given a user entity.
	 *
	 * We only use the `id` property of the user entity, cast to integer, as the Panopticon user ID by which records are
	 * keyed in the database table.
	 *
	 * @param   PublicKeyCredentialUserEntity  $publicKeyCredentialUserEntity  Public key credential user entity record
	 *
	 * @return  CredentialRecord[]
	 * @since   1.2.3
	 */
	public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
	{
		$db         = $this->getDatabase();
		$userHandle = $publicKeyCredentialUserEntity->id;
		$query      = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__passkeys'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userHandle));

		try
		{
			$records = $db->setQuery($query)->loadAssocList();
		}
		catch (Throwable)
		{
			return [];
		}

		$serializer = $this->getCredentialSerializer();

		/**
		 * Converts invalid credential records to CredentialRecord objects, or null if they are invalid.
		 *
		 * This closure is defined as a variable to prevent PHP-CS from getting a stoke trying to
		 * figure out the correct indentation :)
		 *
		 * @param   array  $record  The record to convert
		 *
		 * @return  CredentialRecord|null
		 */
		$recordsMapperClosure = function ($record) use ($serializer) {
			$json = (string) $record['credential'];

			if (empty($json))
			{
				return null;
			}

			try
			{
				return $serializer->deserialize($json, CredentialRecord::class, 'json');
			}
			catch (Throwable)
			{
				return null;
			}
		};

		$records = array_map($recordsMapperClosure, $records);

		/**
		 * Filters the list of records to only keep valid entries.
		 *
		 * Only array members that are CredentialRecord objects survive the filter.
		 *
		 * This closure is defined as a variable to prevent PHP-CS from getting a stoke trying to
		 * figure out the correct indentation :)
		 *
		 * @param   CredentialRecord|mixed  $record  The record to filter
		 *
		 * @return boolean
		 */
		$filterClosure = (fn($record) => !\is_null($record) && \is_object($record) && ($record instanceof CredentialRecord));

		return array_filter($records, $filterClosure);
	}

	/**
	 * Add or update an attested credential for a given user.
	 *
	 * @param   PublicKeyCredentialSource  $publicKeyCredentialSource  The public key credential source to store
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.2.3
	 */
	public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
	{
		$lang = Factory::getContainer()->language;

		// Default values for saving a new credential source
		$defaultName  = $lang->text('PANOPTICON_PASSKEYS_LBL_DEFAULT_AUTHENTICATOR');
		$credentialId = base64_encode($publicKeyCredentialSource->publicKeyCredentialId);
		$user         = Factory::getContainer()->userManager->getUser();
		$userHandle   = $publicKeyCredentialSource->userHandle ?: $this->getHandleFromUserId($user->getId());
		$o            = (object) [
			'id'         => $credentialId,
			'user_id'    => $userHandle,
			'label'      => $lang->sprintf(
				'PANOPTICON_PASSKEYS_LBL_DEFAULT_AUTHENTICATOR_LABEL',
				$defaultName,
				$this->formatDate('now')
			),
			'credential' => $this->getCredentialSerializer()->serialize($publicKeyCredentialSource, 'json'),
		];
		$update       = false;

		$db = $this->getDatabase();

		// Try to find an existing record
		try
		{
			$query     = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__passkeys'))
				->where($db->quoteName('id') . ' = ' . $db->quote($credentialId));
			$oldRecord = $db->setQuery($query)->loadObject();

			if (\is_null($oldRecord))
			{
				throw new Exception('This is a new record');
			}

			/**
			 * Sanity check. The existing credential source must have the same user handle as the one I am trying to
			 * save. Otherwise something fishy is going on.
			 */
			// phpcs:ignore
			if ($oldRecord->user_id != $publicKeyCredentialSource->userHandle)
			{
				throw new \RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CREDENTIAL_ID_ALREADY_IN_USE'));
			}

			// phpcs:ignore
			$o->user_id = $oldRecord->user_id;
			$o->label   = $oldRecord->label;
			$update     = true;
		}
		catch (Exception)
		{
		}

		if ($update)
		{
			$db->updateObject('#__passkeys', $o, ['id']);

			return;
		}

		/**
		 * This check is deliberately skipped for updates. When logging in the underlying library will try to save the
		 * credential source. This is necessary to update the last known authenticator signature counter which prevents
		 * replay attacks. When we are saving a new record, though, we have to make sure we are not a guest user. Hence
		 * the check below.
		 */
		if ((\is_null($user) || !$user->getId()))
		{
			throw new \RuntimeException($lang->text('PANOPTICON_PASSKEYS_ERR_CANT_STORE_FOR_GUEST'));
		}

		$db->insertObject('#__passkeys', $o);
	}

	/**
	 * Get all credential information for a given user ID. This is meant to only be used for displaying records.
	 *
	 * @param   int  $userId  The user ID
	 *
	 * @return  array<CredentialRecord|null>
	 *
	 * @since   1.2.3
	 */
	public function getAll(int $userId): array
	{
		$db         = $this->getDatabase();
		$userHandle = $this->getHandleFromUserId($userId);
		$query      = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__passkeys'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userHandle));

		try
		{
			$results = $db->setQuery($query)->loadAssocList();
		}
		catch (Exception)
		{
			return [];
		}

		if (empty($results))
		{
			return [];
		}

		$serializer = $this->getCredentialSerializer();

		/**
		 * Decodes the credentials on each record.
		 *
		 * @param   array  $record  The record to convert
		 *
		 * @return  array
		 */
		$recordsMapperClosure = function ($record) use ($serializer) {
			$json = (string) $record['credential'];

			if (empty($json))
			{
				$record['credential'] = null;

				return $record;
			}

			try
			{
				$record['credential'] = $serializer->deserialize($json, CredentialRecord::class, 'json');

				return $record;
			}
			catch (Throwable)
			{
				$record['credential'] = null;

				return $record;
			}
		};

		return array_map($recordsMapperClosure, $results);
	}

	/**
	 * Do we have stored credentials under the specified Credential ID?
	 *
	 * @param   string  $credentialId  The ID of the credential to check for existence
	 *
	 * @return  boolean
	 *
	 * @since   1.2.3
	 */
	public function has(string $credentialId): bool
	{
		$db           = $this->getDatabase();
		$credentialId = base64_encode($credentialId);
		$query        = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__passkeys'))
			->where($db->quoteName('id') . ' = ' . $db->quote($credentialId));

		try
		{
			$count = $db->setQuery($query)->loadResult();

			return $count > 0;
		}
		catch (Exception)
		{
			return false;
		}
	}

	/**
	 * Update the human-readable label of a credential
	 *
	 * @param   string  $credentialId  The credential ID
	 * @param   string  $label         The human-readable label to set
	 *
	 * @return  void
	 *
	 * @since   1.2.3.
	 */
	public function setLabel(string $credentialId, string $label): void
	{
		$db           = $this->getDatabase();
		$credentialId = base64_encode($credentialId);
		$o            = (object) [
			'id'    => $credentialId,
			'label' => $label,
		];

		$db->updateObject('#__passkeys', $o, ['id'], false);
	}

	/**
	 * Remove stored credentials
	 *
	 * @param   string  $credentialId  The credentials ID to remove
	 *
	 * @return  void
	 *
	 * @since   1.2.3
	 */
	public function remove(string $credentialId): void
	{
		if (!$this->has($credentialId))
		{
			return;
		}

		$db           = $this->getDatabase();
		$credentialId = base64_encode($credentialId);
		$query        = $db->getQuery(true)
			->delete($db->quoteName('#__passkeys'))
			->where($db->quoteName('id') . ' = ' . $db->quote($credentialId));

		$db->setQuery($query)->execute();
	}

	/**
	 * Return the user handle for the stored credential given its ID.
	 *
	 * The user handle must not be personally identifiable. Per https://w3c.github.io/webauthn/#user-handle it is
	 * acceptable to have a salted hash with a salt private to our server, e.g. Joomla's secret. The only immutable
	 * information in Panopticon is the user ID so that's what we will be using.
	 *
	 * @param   string  $credentialId  The credential ID to get the user handle for
	 *
	 * @return  string
	 *
	 * @since   1.2.3
	 */
	public function getUserHandleFor(string $credentialId): string
	{
		$publicKeyCredentialSource = $this->findOneByCredentialId($credentialId);

		if (empty($publicKeyCredentialSource))
		{
			return '';
		}

		return $publicKeyCredentialSource->userHandle;
	}

	/**
	 * Return a user handle given an integer Joomla user ID.
	 *
	 * We use the HMAC-SHA-256 of the user ID with the site's secret as the key. Using it instead of SHA-512 is on
	 * purpose! WebAuthn only allows user handles up to 64 bytes long.
	 *
	 * @param   int  $id  The user ID to convert
	 *
	 * @return  string  The user handle (HMAC-SHA-256 of the user ID)
	 *
	 * @since   1.2.3
	 */
	public function getHandleFromUserId(int $id): string
	{
		$key  = $this->getEncryptionKey();
		$data = sprintf('%010u', $id);

		return hash_hmac('sha256', $data, $key, false);
	}

	/**
	 * Get the user ID from the user handle
	 *
	 * This is a VERY inefficient method. Since the user handle is an HMAC-SHA-256 of the user ID we can't just go
	 * directly from a handle back to an ID. We have to iterate all user IDs, calculate their handles and compare them
	 * to the given handle.
	 *
	 * To prevent a lengthy infinite loop in case of an invalid user handle we don't iterate the entire 2+ billion valid
	 * 32-bit integer range. We load the user IDs of active users and iterate through them.
	 *
	 * To avoid memory outage on large sites with thousands of active user records we load up to 10,000 users at a time.
	 * Each block of 10,000 user IDs takes about 60-80 msec to iterate. On a site with 200,000 active users this method
	 * will take less than 1.5 seconds. This is slow but not impractical, even on crowded shared hosts with a quarter of
	 * the performance of my test subject (a mid-range, shared hosting server).
	 *
	 * @param   string|null  $userHandle  The user handle which will be converted to a user ID.
	 *
	 * @return  integer|null
	 * @since   1.2.3
	 */
	public function getUserIdFromHandle(?string $userHandle): ?int
	{
		if (empty($userHandle))
		{
			return null;
		}

		$db = $this->getDatabase();

		// Check that the userHandle does exist in the database
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__passkeys'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userHandle));

		try
		{
			$numRecords = $db->setQuery($query)->loadResult();
		}
		catch (Exception)
		{
			return null;
		}

		if (is_null($numRecords) || ($numRecords < 1))
		{
			return null;
		}

		// Prepare the query
		$query = $db->getQuery(true)
			->select([$db->quoteName('id')])
			->from($db->quoteName('#__users'));

		$key   = $this->getEncryptionKey();
		$start = 0;
		$limit = 10000;

		while (true)
		{
			try
			{
				$ids = $db->setQuery($query, $start, $limit)->loadColumn();
			}
			catch (Exception)
			{
				return null;
			}

			if (empty($ids))
			{
				return null;
			}

			foreach ($ids as $userId)
			{
				$data       = sprintf('%010u', $userId);
				$thisHandle = hash_hmac('sha256', $data, $key, false);

				if ($thisHandle == $userHandle)
				{
					return $userId;
				}
			}

			$start += $limit;
		}
	}

	/**
	 * Create a Symfony Serializer configured for WebAuthn credential source serialization.
	 *
	 * @return  SerializerInterface
	 * @since   1.2.3
	 */
	private function getCredentialSerializer(): SerializerInterface
	{
		$asSM = new AttestationStatementSupportManager();
		$asSM->add(new NoneAttestationStatementSupport());

		return (new WebauthnSerializerFactory($asSM))->create();
	}

	/**
	 * Get the site's secret, used as an encryption key
	 *
	 * @return  string
	 *
	 * @since   1.2.3
	 */
	private function getEncryptionKey(): string
	{
		try
		{
			return Factory::getContainer()->appConfig->get('secret', '');
		}
		catch (Exception)
		{
			return '';
		}
	}

	private function getDatabase(): Driver
	{
		return Factory::getContainer()->db;
	}

	/**
	 * Format a date for display.
	 *
	 * @param   string|\DateTime  $date     The date to format
	 * @param   string|null       $format   The format string, default is DATE_FORMAT_LC6
	 * @param   bool              $tzAware  Should the format be timezone aware? See notes above.
	 *
	 * @return  string
	 * @since   1.2.3
	 */
	private function formatDate($date, ?string $format = null, bool $tzAware = true): string
	{
		$format ??= Factory::getContainer()->language->text('DATE_FORMAT_LC6');

		return Factory::getContainer()->html->basic->date($date, $format, $tzAware);
	}

}
