<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\WebAuthn\Repository;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Mfa as MfaModel;
use Awf\Mvc\DataModel;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

defined('AKEEBA') || die;

class MFA implements PublicKeyCredentialSourceRepository
{
	/**
	 * The user ID we will operate with
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	private int $userId = 0;

	/**
	 * Constructor.
	 *
	 * @param   int|null  $userid  The user ID this repository will be working with.
	 *
	 * @since   1.0.0
	 */
	public function __construct(?int $userid = null)
	{
		if (empty($userid))
		{
			$userid = Factory::getContainer()->userManager->getUser()->getId();
		}

		$this->userId = $userid;
	}

	public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
	{
		$publicKeyCredentialUserEntity = new PublicKeyCredentialUserEntity('', $this->userId, '', '');

		$credentials = $this->findAllForUserEntity($publicKeyCredentialUserEntity);

		return array_reduce(
			$credentials,
			fn($carry, $record) => $carry ?? (
			$record->getAttestedCredentialData()->getCredentialId() == $publicKeyCredentialId
				? $record
				: null
			),
			null
		);
	}

	public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
	{
		$userId = $publicKeyCredentialUserEntity->getId();

		$results = DataModel::getTmpInstance('', 'Mfa')
			->where('user_id', values: $userId)
			->get(true)
			->filter(fn(MfaModel $mfa) => !empty($mfa->options));

		$numBackupCodes = $results
			->filter(fn(MfaModel $mfa) => $mfa->method === 'backupcodes')
			->count();

		// Having only backup codes is as good as having no methods.
		if ($results->count() < 1 || $numBackupCodes === $results->count())
		{
			return [];
		}

		$arrayKeys   = $results->modelKeys();
		$arrayValues = array_map(
			function (MfaModel $result) use ($userId) {
				$options = $result->getOptions();

				if (empty($options) || !isset($options['pubkeysource']))
				{
					return null;
				}

				if (is_string($options['pubkeysource']))
				{
					$options['pubkeysource'] = json_decode($options['pubkeysource'], true);

					return PublicKeyCredentialSource::createFromArray($options['pubkeysource']);
				}
				elseif (is_array($options['pubkeysource']))
				{
					return PublicKeyCredentialSource::createFromArray($options['pubkeysource']);
				}

				return null;
			},
			$results->toArray()
		);

		unset($results);

		return array_filter(
			array_combine($arrayKeys, $arrayValues)
		);
	}

	public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
	{
		// I can only create or update credentials for the user this class was created for
		if ($publicKeyCredentialSource->getUserHandle() != $this->userId)
		{
			throw new \RuntimeException('Cannot create or update WebAuthn credentials for a different user.', 403);
		}

		// Do I have an existing record for this credential?
		$recordId                      = null;
		$publicKeyCredentialUserEntity = new PublicKeyCredentialUserEntity('', $this->userId, '', '');
		$credentials                   = $this->findAllForUserEntity($publicKeyCredentialUserEntity);

		foreach ($credentials as $id => $record)
		{
			if ($record->getAttestedCredentialData()->getCredentialId() !=
				$publicKeyCredentialSource->getAttestedCredentialData()->getCredentialId())
			{
				continue;
			}

			$recordId = $id;

			break;
		}

		$mfaModel = DataModel::getTmpInstance('', 'Mfa');

		if ($recordId)
		{
			$mfaModel->findOrFail($recordId);

			$options = $mfaModel->getOptions();

			if (isset($options['attested']))
			{
				unset($options['attested']);
			}

			$options['pubkeysource'] = $publicKeyCredentialSource;
			$mfaModel->save(
				[
					'options' => json_encode($options),
				]
			);
		}
		else
		{
			$mfaModel->reset();
			$mfaModel->save(
				[
					'user_id' => $this->userId,
					'title'   => 'WebAuthn auto-save',
					'method'  => 'webauthn',
					'default' => 0,
					'options' => json_encode(['pubkeysource' => $publicKeyCredentialSource]),
				]
			);
		}
	}
}