<?php

namespace Tests\Base\Auth;

use App\Entity\User as AppUser;
use Base\Auth\UserAccounts;
use Base\Auth\UserIdentities;
use Base\Auth\UserPasskeys;
use Base\Entity\User\Identity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Omniauth\Passkey\PasskeyRecord;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Http\HttpTestTrait;

/**
 * What the stores of Base\Auth\ read and write that a sign-in alone does not
 * show (OmniauthOnOmnibaseTest signs in through all of them): the
 * application's identifier field, the userIdentity table's shape and its
 * keys, a passkey's guards. Against the harness's test database.
 */
final class StoresTest extends KernelTestCase
{
    use HttpTestTrait;
    use KeepsServerGlobals;

    protected function setUp(): void
    {
        $this->keepServerGlobals();
        $this->bootHost();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $user) {
            $this->entityManager()->getConnection()->executeStatement('DELETE FROM userIdentity WHERE user_id = ?', [$user->getId()]);
        }
        $this->entityManager()->clear();
        $this->removeUsers();
        $this->restoreServerGlobals();
        parent::tearDown();
    }

    public function testAnAccountIsFoundByItsAddressThenByTheApplicationsIdentifier(): void
    {
        $user = $this->createUser();
        $accounts = new UserAccounts($this->entityManager());

        self::assertSame($user, $accounts->byIdentifier(' '.strtoupper($user->getEmail()).' '));
        self::assertNull($accounts->byIdentifier($user->getUsername()), 'the harness identifies its members by address');
        self::assertNull($accounts->byIdentifier(''));
        self::assertSame($user, $accounts->byHandle((string) $user->getId()));
        self::assertNull($accounts->byHandle('1 OR 1=1'));
        self::assertSame([(string) $user->getId(), $user->getEmail(), $user->getEmail()], [$accounts->handle($user), $accounts->identifier($user), $accounts->email($user)]);

        // An application whose identifier is the user name (lesringostars): found by it too.
        $field = AppUser::$userIdentifier;
        AppUser::$userIdentifier = 'username';
        try {
            self::assertSame($user, $accounts->byIdentifier($user->getUsername()));
            self::assertSame($user->getUsername(), $accounts->identifier($user));
            self::assertSame($user, $accounts->byIdentifier($user->getEmail()));
        } finally {
            AppUser::$userIdentifier = $field;
        }
    }

    public function testTheIdentitiesTableAndItsTwoKeys(): void
    {
        $metadata = $this->entityManager()->getClassMetadata(Identity::class);
        self::assertSame('userIdentity', $metadata->getTableName());
        self::assertSame(['id', 'provider', 'subject', 'email', 'emailVerified', 'createdAt', 'lastUsedAt'], array_values($metadata->getColumnNames()));
        self::assertEquals(['name' => 'user_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE', 'nullable' => false], array_intersect_key((array) $metadata->getAssociationMapping('user')['joinColumns'][0], array_flip(['name', 'referencedColumnName', 'onDelete', 'nullable'])));

        $ringo = $this->createUser();
        $paul = $this->createUser();
        $identities = new UserIdentities($this->entityManager());
        $identities->link($ringo, 'google', 'sub-'.$ringo->getId(), 'Ringo@Example.org', true);
        $identities->link($ringo, 'google', 'sub-'.$ringo->getId());
        self::assertCount(1, $identities->of($ringo), 'linked again: nothing changes');
        self::assertSame('ringo@example.org', $identities->of($ringo)[0]->getEmail());
        self::assertNull($identities->of($ringo)[0]->getLastUsedAt());
        $identities->used('google', 'sub-'.$ringo->getId(), new \DateTimeImmutable('2026-10-21 12:00:00 UTC'));
        $this->entityManager()->clear();
        self::assertSame('2026-10-21 12:00:00', $identities->of($ringo)[0]->getLastUsedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));

        try {
            $identities->link($paul, 'google', 'sub-'.$ringo->getId());
            self::fail('a subject linked to two accounts');
        } catch (\LogicException) {
            self::assertSame([], $identities->providers($paul));
        }

        // The table refuses what the store does not ask it: two accounts, one subject; two identities of a provider on one account.
        $connection = $this->entityManager()->getConnection();
        $insert = 'INSERT INTO userIdentity (user_id, provider, subject, email, emailVerified, createdAt) VALUES (?, ?, ?, NULL, 0, ?)';
        foreach ([[$paul->getId(), 'google', 'sub-'.$ringo->getId()], [$ringo->getId(), 'google', 'another']] as $row) {
            try {
                $connection->executeStatement($insert, [...$row, '2026-10-21 12:00:00']);
                self::fail('the table took '.implode(', ', $row));
            } catch (UniqueConstraintViolationException) {
                $this->addToAssertionCount(1);
            }
        }
        $identities->link($ringo, 'facebook', 'fb-1');
        self::assertSame(['facebook', 'google'], $identities->providers($ringo));
    }

    public function testAPasskeyBelongsToTheAccountItsHandleNames(): void
    {
        $ringo = $this->createUser();
        $paul = $this->createUser();
        $passkeys = new UserPasskeys($this->entityManager());
        $record = new PasskeyRecord(base64_encode(random_bytes(32)), credentialPublicKey: base64_encode(random_bytes(77)), userHandle: (string) $ringo->getId());

        try {
            $passkeys->add($paul, $record);
            self::fail('another account\'s passkey');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $passkeys->of($paul));
        }
        self::assertNull($passkeys->byCredentialId('not base64!'));
        self::assertNull($passkeys->byCredentialId(base64_encode(random_bytes(32))));

        $passkeys->add($ringo, $record, 'Clé');
        try {
            self::assertEquals($record, $passkeys->byCredentialId($record->publicKeyCredentialId), 'the 13 fields read back');
            self::assertEquals([$record], $passkeys->of($ringo));
        } finally {
            $this->entityManager()->getConnection()->executeStatement('DELETE FROM userPasskey WHERE user_id = ?', [$ringo->getId()]);
        }
    }
}
