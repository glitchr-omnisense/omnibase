<?php

namespace Tests\Base\Database\Repository;

use PHPUnit\Framework\TestCase;

/**
 * The core without direct Doctrine on lesringostars' path (audit
 * "lesringostars face au master plan", step R28), read in the tokens of each
 * class that path reaches - measured as the classes whose code lesringostars'
 * application and sign-in suites ran (Xdebug's lines, every process), with the
 * commands its crontab runs (thread:publishable, trash:purge, uploader:entities,
 * timemachine:snapshot:backup):
 *
 * - no ->getRepository() call: the repositories by their interfaces
 *   (Base\Repository\*, Base\Database\Repository\Repositories for a class named
 *   at run time), Doctrine's repositories behind them;
 * - no repository class named (Base\Repository\...Repository, App\Repository\...):
 *   their interfaces instead;
 * - no FlysystemInterface (deprecated): omnibase's storages over omnidrive,
 *   Base\Service\Storages;
 * - no BaseService: BaseTrait's statics are kept by Base\Service\Locator.
 *
 * What stays outside, on purpose, is listed in the plan (Doctrine's own side:
 * the mapping attributes, the Doctrine event subscribers, omniauth's stores on
 * the entity manager, the repositories themselves).
 */
final class CorePathTest extends TestCase
{
    public const PATH = [
        'Attributes/Attribute/Route.php',
        'Attributes/AttributeReader.php',
        'Auth/Listener/WarnNewDevice.php',
        'Bridge/Symfony/AuthListeners.php',
        'Bridge/Symfony/FlysystemFiles.php',
        'Console/Command/ThreadPublishableCommand.php',
        'Console/Command/TimeMachineSnapshotCommand.php',
        'Console/Command/TrashPurgeCommand.php',
        'Console/Command/UploaderEntitiesCommand.php',
        'Controller/Api/ThreadController.php',
        'Controller/Client/ThreadSearchController.php',
        'Controller/Client/UserProfileController.php',
        'Controller/Client/UserSearchController.php',
        'Controller/Client/UserSettingsController.php',
        'Controller/SecurityController.php',
        'Controller/UX/MediaController.php',
        'Database/Attribute/Uploader.php',
        'EntitySubscriber/ThreadSubscriber.php',
        'Field/Type/SelectType.php',
        'Notifier/Abstract/BaseNotifier.php',
        'Notifier/Channel/InAppChannel.php',
        'Security/AccessTokenHandler.php',
        'Security/ConnectionTracker.php',
        'Security/LoginFormAuthenticator.php',
        'Security/UserProvider.php',
        'Security/UserTracker.php',
        'Security/WebauthnUserEntityRepository.php',
        'Service/Analytics.php',
        'Service/FileService.php',
        'Service/FormGuard.php',
        'Service/ImageRoute.php',
        'Service/MediaService.php',
        'Service/Model/Wysiwyg/MediaEnhancer.php',
        'Service/Model/Wysiwyg/MentionEnhancer.php',
        'Service/Model/Wysiwyg/SemanticEnhancer.php',
        'Service/OpeningHours.php',
        'Service/Push/WebPushService.php',
        'Service/Redirections.php',
        'Service/SettingBag.php',
        'Service/TimeMachine.php',
        'Service/TrashManager.php',
        'Service/VersionManager.php',
        'Service/WidgetProvider.php',
        'Subscriber/AnalyticsSubscriber.php',
        'Subscriber/NewDeviceSubscriber.php',
        'Traits/BaseTrait.php',
        'Twig/Extension/HtmlTwigExtension.php',
    ];

    /** Where a ->getRepository() stays: the attribute reader is the mapping attributes' (Doctrine's side). */
    private const GET_REPOSITORY_LEFT = ['Attributes/AttributeReader.php', 'Traits/BaseTrait.php'];

    /** Where the name BaseService stays: BaseTrait::getService() hands the facade itself out. */
    private const BASE_SERVICE_LEFT = ['Traits/BaseTrait.php'];

    public function testThePathAsksNoRepositoryOfTheEntityManager(): void
    {
        foreach (self::PATH as $file) {
            if (\in_array($file, self::GET_REPOSITORY_LEFT, true)) {
                continue;
            }
            $tokens = self::tokens($file);
            foreach ($tokens as $i => $token) {
                if (\is_array($token) && \T_STRING === $token[0] && 'getRepository' === $token[1] && \T_OBJECT_OPERATOR === self::previous($tokens, $i)) {
                    self::fail("$file:{$token[2]} calls ->getRepository()");
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    public function testThePathNamesNoRepositoryClassNorFlysystemInterfaceNorBaseService(): void
    {
        foreach (self::PATH as $file) {
            foreach (self::tokens($file) as $token) {
                if (!\is_array($token) || !\in_array($token[0], [\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $name = ltrim($token[1], '\\');
                $short = substr($name, (int) strrpos('\\'.$name, '\\'));
                if (preg_match('/^[A-Z]\w*Repository$/', $short) && !\in_array($short, ['ServiceEntityRepository', 'EntityRepository', 'ObjectRepository', 'WebauthnUserEntityRepository'], true)) {
                    self::fail("$file:{$token[2]} names the repository class $name");
                }
                if ('FlysystemInterface' === $short) {
                    self::fail("$file:{$token[2]} names the deprecated FlysystemInterface");
                }
                if ('BaseService' === $short && !\in_array($file, self::BASE_SERVICE_LEFT, true)) {
                    self::fail("$file:{$token[2]} names BaseService");
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    public function testBaseTraitKeepsItsStaticsOnTheLocator(): void
    {
        $tokens = self::tokens('Traits/BaseTrait.php');
        foreach ($tokens as $i => $token) {
            if (\is_array($token) && \T_STRING === $token[0] && 'BaseService' === $token[1] && \T_DOUBLE_COLON === self::next($tokens, $i)) {
                $after = $tokens[self::nextIndex($tokens, self::nextIndex($tokens, $i))];
                self::assertSame('class', \is_array($after) ? $after[1] : $after, "BaseTrait.php:{$token[2]} asks BaseService");
            }
        }
        $parent = (new \ReflectionClass(\Base\Service\BaseService::class))->getParentClass();
        self::assertSame(\Base\Service\Locator::class, false !== $parent ? $parent->getName() : null);
    }

    /** @return list<mixed> */
    private static function tokens(string $file): array
    {
        $path = \dirname(__DIR__, 3).'/src/'.$file;
        self::assertFileExists($path);

        return array_values(array_filter(token_get_all((string) file_get_contents($path)), static fn ($t) => !\is_array($t) || !\in_array($t[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)));
    }

    /** @param list<mixed> $tokens */
    private static function previous(array $tokens, int $i): int|string|null
    {
        $t = $tokens[$i - 1] ?? null;

        return \is_array($t) ? $t[0] : $t;
    }

    /** @param list<mixed> $tokens */
    private static function next(array $tokens, int $i): int|string|null
    {
        $t = $tokens[$i + 1] ?? null;

        return \is_array($t) ? $t[0] : $t;
    }

    /** @param list<mixed> $tokens */
    private static function nextIndex(array $tokens, int $i): int
    {
        return $i + 1;
    }
}
