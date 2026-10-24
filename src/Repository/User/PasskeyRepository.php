<?php

namespace Base\Repository\User;

use App\Entity\User;
use Base\Contract\PasskeyRecords;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Database\Type\Passkey\Base64;
use Base\Entity\User\Passkey;

use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\TrustPathDenormalizer;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\TrustPath;

/**
 * The passkeys (Base\Entity\User\Passkey), and WebauthnBundle's credential
 * repository (plan "omniauth", AU1).
 *
 * The bundle's interfaces are declared through Base\Contract\PasskeyRecords
 * (theirs where the bundle is installed, empty elsewhere): the repository
 * loads without the bundle. Since the passkey no longer is a
 * Webauthn\CredentialRecord, the records the bundle asks for are made from
 * the passkeys (credentialRecord()), and what it hands back to save is copied
 * onto the passkey of that credential (fill()), or onto a new one at a
 * registration. This is the one place of the core that speaks webauthn-lib to
 * the passkeys; its methods load the library only when called.
 *
 * Only the current contract: the deprecated PublicKeyCredentialSourceRepositoryInterface
 * alias that WebauthnExtension still registers is removed by
 * WebauthnDeprecatedAliasPass, so nothing requires it here.
 *
 * @method Passkey|null find($id, $lockMode = null, $lockVersion = null)
 * @method Passkey|null findOneBy(array $criteria, array $orderBy = null)
 * @method Passkey[]    findAll()
 * @method Passkey[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PasskeyRepository extends ServiceEntityRepository implements PasskeyRecords, Passkeys
{
    /**
     * The passkey of this credential id (bytes), compared as the column keeps it (base64).
     *
     * Not findOneBy(): omnibase's finder binds a criterion's value as it is, not through the column's
     * type, and the raw id never matched its base64 text - up to AU1 no passkey could sign in
     * ("The credential ID is invalid.").
     */
    public function findOneByCredential(string $publicKeyCredentialId): ?Passkey
    {
        return $this->createQueryBuilder('passkey')
            ->where('passkey.publicKeyCredentialId = :id')
            ->setParameter('id', $publicKeyCredentialId, Base64::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<CredentialRecord> */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return array_map(self::credentialRecord(...), $this->findBy(["userHandle" => $publicKeyCredentialUserEntity->id]));
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        $passkey = $this->findOneByCredential($publicKeyCredentialId);

        return $passkey ? self::credentialRecord($passkey) : null;
    }

    /**
     * What the bundle hands back: after a sign-in, the record of a known passkey (its counter and
     * flags moved), copied onto it; at a registration, a plain record made from the attestation,
     * which becomes the passkey of the account its userHandle names (WebauthnUserEntityRepository
     * gives the account's id as its handle).
     */
    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $entityManager = $this->getEntityManager();

        if ($passkey = $this->findOneByCredential($credentialRecord->publicKeyCredentialId)) {
            self::fill($passkey, $credentialRecord);
            $entityManager->flush();
            return;
        }

        $user = $entityManager->find(User::class, (int) $credentialRecord->userHandle);
        if (!$user) {
            throw new \RuntimeException('Cannot save passkey: unknown user handle.');
        }

        $entityManager->persist(Passkey::createFromCredentialRecord($credentialRecord, $user));
        $entityManager->flush();
    }

    /** webauthn-lib's record of a passkey: what the bundle verifies a sign-in against. */
    public static function credentialRecord(Passkey $passkey): CredentialRecord
    {
        $trustPath = (new TrustPathDenormalizer())->denormalize($passkey->trustPath, TrustPath::class);

        return CredentialRecord::create(
            $passkey->publicKeyCredentialId,
            $passkey->type,
            $passkey->transports,
            $passkey->attestationType,
            $trustPath,
            Uuid::fromString($passkey->aaguid),
            $passkey->credentialPublicKey,
            $passkey->userHandle,
            $passkey->counter,
            $passkey->otherUI,
            $passkey->backupEligible,
            $passkey->backupStatus,
            $passkey->uvInitialized,
        );
    }

    /**
     * The 13 fields of a record, on the passkey, in the values the passkey keeps: the trust path as
     * webauthn-lib normalizes it (what the bundle's `trust_path` type wrote), the AAGUID as its text.
     */
    public static function fill(Passkey $passkey, CredentialRecord $record): Passkey
    {
        $passkey->publicKeyCredentialId = $record->publicKeyCredentialId;
        $passkey->type = $record->type;
        $passkey->transports = $record->transports;
        $passkey->attestationType = $record->attestationType;
        $passkey->trustPath = (new TrustPathDenormalizer())->normalize($record->trustPath);
        $passkey->aaguid = $record->aaguid->toRfc4122();
        $passkey->credentialPublicKey = $record->credentialPublicKey;
        $passkey->userHandle = $record->userHandle;
        $passkey->counter = $record->counter;
        $passkey->otherUI = $record->otherUI;
        $passkey->backupEligible = $record->backupEligible;
        $passkey->backupStatus = $record->backupStatus;
        $passkey->uvInitialized = $record->uvInitialized;

        return $passkey;
    }
}
