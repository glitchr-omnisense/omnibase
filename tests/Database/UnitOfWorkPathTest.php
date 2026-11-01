<?php

namespace Tests\Base\Database;

use PHPUnit\Framework\TestCase;

/**
 * The core's services on the unit of work (plan "omnibase agnostic", step
 * F2d), read in their tokens: they persist, remove, flush and read through
 * omnisense/omnibridge's UnitOfWork, their repositories through
 * Base\Database\UnitOfWorks::repositories() or an interface, an entity's
 * fields through its shape, and what only a persistence does (a row locked,
 * a field compared and set, the second-level cache) through
 * Base\Database\Backend\*. Doctrine's entity manager is named only as the
 * deprecated other half of a constructor's union type
 * (UnitOfWork|EntityManagerInterface, until 6.0).
 *
 * What stays on the entity manager, on purpose (Doctrine's own side, for step
 * F3, or host code for F5): the mapping (ClassMetadataManipulator,
 * ClassMetadataFactory, EntityHydrator), ServiceEntityRepository, the Doctrine
 * listeners (src/DatabaseSubscriber, src/EntitySubscriber), the commands of
 * Doctrine's own (DoctrineDatabaseImportCommand, DoctrineArrayUpgradeCommand,
 * DoctrineSchemaCharsetCommand), Base\Bridge\Symfony\FrameworkLocator (BaseTrait's
 * framework services since step F2f), the validators and
 * CropperType (the form layer, F5b), AbstractEventDispatcher and BaseNotifier
 * (F5a), DemoAccountFactory (Doctrine's fixtures).
 */
final class UnitOfWorkPathTest extends TestCase
{
    public const FILES = [
        'Auth/Listener/TrackConnections.php',
        'Auth/Listener/UpdateAccount.php',
        'Auth/Listener/WelcomeBack.php',
        'Auth/UserAccounts.php',
        'Auth/UserIdentities.php',
        'Auth/UserLinkTokens.php',
        'Auth/UserPasskeys.php',
        'Auth/UserStore.php',
        'Console/Command/AutocompleteEntitiesCommand.php',
        'Console/Command/IconEntitiesCommand.php',
        'Console/Command/ThreadPublishableCommand.php',
        'Console/Command/UploaderImagesCropCommand.php',
        'Console/Command/UserCreateCommand.php',
        'Console/Command/UserNotificationCommand.php',
        'Controller/Api/ThreadController.php',
        'Controller/Client/NotificationController.php',
        'Controller/LocalizerController.php',
        'Controller/SecurityController.php',
        'Controller/UX/AutocompleteController.php',
        'Controller/UX/AutovalidateController.php',
        'Notifier/Channel/InAppChannel.php',
        'Security/ConnectionTracker.php',
        'Security/UserChecker.php',
        'Security/UserTracker.php',
        'Service/Collab/CollabRoomResolver.php',
        'Service/GroupManager.php',
        'Service/Invitations.php',
        'Service/Model/Wysiwyg/HeadingEnhancer.php',
        'Service/Push/WebPushService.php',
        'Service/SettingBag.php',
        'Service/TrashManager.php',
        'Service/Signatures.php',
        'Service/VersionManager.php',
    ];

    /** What only the entity manager answers: none of these is called on anything in those files. */
    private const ENTITY_MANAGER_ONLY = ['getRepository', 'getClassMetadata', 'getMetadataFactory', 'getCache', 'createQuery', 'createQueryBuilder', 'beginTransaction', 'commit', 'rollback', 'lock', 'getFilters', 'getConnection', 'getUnitOfWork', 'detach'];

    public function testTheyAskTheEntityManagerNothing(): void
    {
        foreach (self::FILES as $file) {
            $tokens = self::tokens($file);
            foreach ($tokens as $i => $token) {
                if (!\is_array($token) || \T_STRING !== $token[0] || !\in_array($token[1], self::ENTITY_MANAGER_ONLY, true)) {
                    continue;
                }
                $previous = $tokens[$i - 1] ?? null;
                if (\is_array($previous) && \in_array($previous[0], [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR], true)) {
                    self::fail("$file:{$token[2]} calls ->{$token[1]}()");
                }
            }
            foreach ($tokens as $token) {
                if (\is_array($token) && \T_VARIABLE !== $token[0] && \T_STRING === $token[0] && 'entityManager' === $token[1]) {
                    self::fail("$file:{$token[2]} reads an \$entityManager property");
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    /**
     * Their parent, Base\Console\Command, takes the entity manager still (the applications' commands read its
     * deprecated $entityManager; it moves to omnibridge/symfony at step F5c): they pass it on, and ask $unitOfWork.
     */
    private const PASS_THE_ENTITY_MANAGER_ON = ['Console/Command/AutocompleteEntitiesCommand.php', 'Console/Command/IconEntitiesCommand.php'];

    public function testTheEntityManagerIsOnlyTheDeprecatedHalfOfAUnion(): void
    {
        foreach (array_diff(self::FILES, self::PASS_THE_ENTITY_MANAGER_ON) as $file) {
            $tokens = self::tokens($file);
            foreach ($tokens as $i => $token) {
                if (!\is_array($token) || !\in_array($token[0], [\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED], true) || !str_ends_with($token[1], 'EntityManagerInterface')) {
                    continue;
                }
                $previous = $tokens[$i - 1] ?? null;
                $inUse = \is_array($tokens[$i - 1] ?? null) && \T_USE === $tokens[$i - 1][0];
                if (!$inUse && '|' !== $previous) {
                    self::fail("$file:{$token[2]} names EntityManagerInterface outside a UnitOfWork|EntityManagerInterface union");
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    /** @return list<mixed> */
    private static function tokens(string $file): array
    {
        $path = \dirname(__DIR__, 2).'/src/'.$file;
        self::assertFileExists($path);

        return array_values(array_filter(token_get_all((string) file_get_contents($path)), static fn ($t) => !\is_array($t) || !\in_array($t[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)));
    }
}
