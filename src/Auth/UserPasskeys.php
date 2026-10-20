<?php

namespace Base\Auth;

use Base\Entity\User\Passkey;
use Base\Repository\User\PasskeyRepository;
use Omniauth\Passkey\Contract\Passkeys;
use Omniauth\Passkey\PasskeyRecord;

/**
 * omniauth/passkey's passkeys: the userPasskey table through
 * Base\Entity\User\Passkey, which the core describes since AU1. The record
 * holds the credential id and the public key as base64 - the text of the
 * columns -, the entity their bytes (the passkey_base64 type turns one into
 * the other); the other eleven fields are the same values. A passkey added
 * here is a row web-auth/webauthn-symfony-bundle reads, and one it wrote is a
 * record here (omniauth/passkey's tests prove both ways on those very
 * columns).
 */
class UserPasskeys extends UserStore implements Passkeys
{
    public function of(object $account): iterable
    {
        $passkeys = $this->repository()->createQueryBuilder('passkey')
            ->where('passkey.user = :user')
            ->setParameter('user', $this->user($account))
            ->orderBy('passkey.id')
            ->getQuery()
            ->getResult();

        return array_map(self::record(...), $passkeys);
    }

    public function byCredentialId(string $credentialId): ?PasskeyRecord
    {
        $passkey = $this->passkey($credentialId);

        return null !== $passkey ? self::record($passkey) : null;
    }

    public function add(object $account, PasskeyRecord $record, ?string $label = null): void
    {
        $user = $this->user($account);
        if ($record->userHandle !== (string) $user->getId()) {
            throw new \InvalidArgumentException('A passkey belongs to the account its user handle names.');
        }
        $passkey = new Passkey();
        self::fill($passkey, $record);
        $passkey->setUser($user);
        $passkey->setLabel(null === $label ? null : mb_substr($label, 0, 100));

        $this->entityManager->persist($passkey);
        $this->save();
    }

    public function used(PasskeyRecord $record): void
    {
        $passkey = $this->passkey($record->publicKeyCredentialId);
        if (null === $passkey) {
            return;
        }
        $passkey->counter = $record->counter;
        $passkey->backupEligible = $record->backupEligible;
        $passkey->backupStatus = $record->backupStatus;
        $passkey->uvInitialized = $record->uvInitialized;
        $passkey->touch();
        $this->save();
    }

    /** The record of a passkey, in the values of its columns. */
    public static function record(Passkey $passkey): PasskeyRecord
    {
        return new PasskeyRecord(
            base64_encode($passkey->publicKeyCredentialId),
            $passkey->type,
            array_values($passkey->transports),
            $passkey->attestationType,
            $passkey->trustPath,
            $passkey->aaguid,
            base64_encode($passkey->credentialPublicKey),
            $passkey->userHandle,
            $passkey->counter,
            $passkey->otherUI,
            $passkey->backupEligible,
            $passkey->backupStatus,
            $passkey->uvInitialized,
        );
    }

    /** A record's 13 fields on a passkey, the credential id and the public key as bytes. */
    public static function fill(Passkey $passkey, PasskeyRecord $record): Passkey
    {
        $passkey->publicKeyCredentialId = $record->credentialId();
        $passkey->type = $record->type;
        $passkey->transports = $record->transports;
        $passkey->attestationType = $record->attestationType;
        $passkey->trustPath = $record->trustPath;
        $passkey->aaguid = $record->aaguid;
        $passkey->credentialPublicKey = $record->publicKey();
        $passkey->userHandle = $record->userHandle;
        $passkey->counter = $record->counter;
        $passkey->otherUI = $record->otherUI;
        $passkey->backupEligible = $record->backupEligible;
        $passkey->backupStatus = $record->backupStatus;
        $passkey->uvInitialized = $record->uvInitialized;

        return $passkey;
    }

    private function passkey(string $credentialId): ?Passkey
    {
        $bytes = base64_decode($credentialId, true);

        return false === $bytes || '' === $bytes ? null : $this->repository()->findOneByCredential($bytes);
    }

    private function repository(): PasskeyRepository
    {
        return $this->entityManager->getRepository(Passkey::class);
    }
}
