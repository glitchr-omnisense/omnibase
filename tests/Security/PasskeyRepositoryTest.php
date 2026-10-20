<?php

namespace Tests\Base\Security;

use Base\Entity\User\Passkey;
use Base\Repository\User\PasskeyRepository;
use Base\Security\WebauthnUserEntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\CertificateTrustPath;
use Webauthn\TrustPath\EmptyTrustPath;
use Tests\Base\Http\HttpTestTrait;

/**
 * The passkeys as WebauthnBundle meets them (plan "omniauth", AU1): the
 * passkey is no longer a Webauthn\CredentialRecord, so PasskeyRepository makes
 * the records the bundle asks for from the passkeys and copies onto them what
 * it hands back - at a registration a new passkey of the account its handle
 * names, after a sign-in the counter and flags of the passkey of that
 * credential. And it finds a credential by its id: omnibase's findOneBy()
 * bound the raw id where the column holds its base64 text, and found nothing
 * (no passkey could sign in, measured on lesringostars).
 *
 * Against the host application's database (the harness: a fresh SQLite one),
 * the account and its passkeys removed after.
 */
final class PasskeyRepositoryTest extends KernelTestCase
{
    use HttpTestTrait;

    protected function setUp(): void
    {
        $this->bootHost();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $user) {
            $this->entityManager()->getConnection()->executeStatement('DELETE FROM userPasskey WHERE userHandle = ?', [(string) $user->getId()]);
        }
        $this->removeUsers();
        parent::tearDown();
    }

    private function repository(): PasskeyRepository
    {
        return $this->entityManager()->getRepository(Passkey::class);
    }

    private static function record(string $userHandle, int $counter = 0, ?CredentialRecord $like = null): CredentialRecord
    {
        return CredentialRecord::create(
            $like?->publicKeyCredentialId ?? random_bytes(32),
            'public-key',
            ['internal', 'hybrid'],
            'none',
            $like?->trustPath ?? EmptyTrustPath::create(),
            $like?->aaguid ?? Uuid::fromString('adce0002-35bc-c60a-648b-0b25f1f05503'),
            $like?->credentialPublicKey ?? random_bytes(77),
            $userHandle,
            $counter,
            null,
            true,
            false,
            true,
        );
    }

    public function testARegistrationBecomesAPasskeyOfTheAccountItsHandleNames(): void
    {
        $user = $this->createUser();
        $record = self::record((string) $user->getId());

        $this->repository()->saveCredentialRecord($record);

        $this->entityManager()->clear();
        $passkeys = $this->repository()->findBy(['userHandle' => (string) $user->getId()]);
        self::assertCount(1, $passkeys);
        [$passkey] = $passkeys;
        self::assertSame($user->getId(), $passkey->getUser()->getId());
        self::assertSame($record->publicKeyCredentialId, $passkey->publicKeyCredentialId, 'bytes in PHP');
        self::assertSame('adce0002-35bc-c60a-648b-0b25f1f05503', $passkey->aaguid, 'a UUID\'s text');
        self::assertSame([], $passkey->trustPath, 'EmptyTrustPath, normalized');
        self::assertSame([true, false, true], [$passkey->backupEligible, $passkey->backupStatus, $passkey->uvInitialized]);
        self::assertNotNull($passkey->getCreatedAt());

        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT publicKeyCredentialId, trustPath, aaguid, credentialPublicKey, transports FROM userPasskey WHERE id = ?', [$passkey->getId()]);
        self::assertSame([
            'publicKeyCredentialId' => base64_encode($record->publicKeyCredentialId),
            'trustPath' => '[]',
            'aaguid' => 'adce0002-35bc-c60a-648b-0b25f1f05503',
            'credentialPublicKey' => base64_encode($record->credentialPublicKey),
            'transports' => '["internal","hybrid"]',
        ], $row, 'the text WebauthnBundle\'s types wrote');
    }

    public function testTheBundleFindsTheRecordOfACredentialAndOfAnAccount(): void
    {
        $user = $this->createUser();
        $record = self::record((string) $user->getId(), 3);
        $this->repository()->saveCredentialRecord($record);
        $this->entityManager()->clear();

        $found = $this->repository()->findOneByCredentialId($record->publicKeyCredentialId);
        self::assertInstanceOf(CredentialRecord::class, $found);
        self::assertNotInstanceOf(Passkey::class, $found);
        self::assertEquals($record, $found, 'the same 13 fields, the objects of webauthn-lib made again');
        self::assertNull($this->repository()->findOneByCredentialId(random_bytes(32)));
        self::assertNull($this->repository()->findOneBy(['publicKeyCredentialId' => $record->publicKeyCredentialId]), 'omnibase\'s finder binds the raw id: why findOneByCredential() exists');

        $account = PublicKeyCredentialUserEntity::create('someone', (string) $user->getId(), 'Someone');
        self::assertEquals([$record], $this->repository()->findAllForUserEntity($account));
    }

    public function testASignInSavesTheCounterOnTheSamePasskey(): void
    {
        $user = $this->createUser();
        $record = self::record((string) $user->getId());
        $this->repository()->saveCredentialRecord($record);
        $this->entityManager()->clear();

        $signedIn = $this->repository()->findOneByCredentialId($record->publicKeyCredentialId);
        $signedIn->counter = 7;            // what webauthn-lib's validator does after a sign-in
        $signedIn->backupStatus = true;
        $this->repository()->saveCredentialRecord($signedIn);

        $this->entityManager()->clear();
        $passkeys = $this->repository()->findBy(['userHandle' => (string) $user->getId()]);
        self::assertCount(1, $passkeys, 'the same passkey, no second one');
        self::assertSame([7, true], [$passkeys[0]->counter, $passkeys[0]->backupStatus]);
    }

    public function testACertificateTrustPathIsKeptAsWebauthnLibNormalizesIt(): void
    {
        $user = $this->createUser();
        $record = self::record((string) $user->getId());
        $record->trustPath = CertificateTrustPath::create(['MIIB/w==', 'MIIC']);
        $this->repository()->saveCredentialRecord($record);
        $this->entityManager()->clear();

        self::assertSame('{"x5c":["MIIB\/w==","MIIC"]}', $this->entityManager()->getConnection()->fetchOne('SELECT trustPath FROM userPasskey WHERE userHandle = ?', [(string) $user->getId()]));
        self::assertEquals($record->trustPath, $this->repository()->findOneByCredentialId($record->publicKeyCredentialId)->trustPath);
    }

    public function testTheAccountsAreThePasskeysUsers(): void
    {
        $user = $this->createUser();
        $users = static::getContainer()->has(WebauthnUserEntityRepository::class)
            ? static::getContainer()->get(WebauthnUserEntityRepository::class)
            : new WebauthnUserEntityRepository($this->entityManager()->getRepository($user::class));

        $entity = $users->findOneByUserHandle((string) $user->getId());
        self::assertSame((string) $user->getId(), $entity->id);
        self::assertSame($user->getUserIdentifier(), $entity->name);
    }
}
