<?php

namespace Tests\Base\Service;

use Base\Repository\Layout\Settings;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBag;
use Omnibridge\Persistence\Persistences;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

/** A persistence's exception of an entity whose row is gone, as a test registers it. */
final class SettingRowGone extends \RuntimeException
{
}

/**
 * SettingBag::getRaw() reads a path again without the cache when a cached setting's row is gone, whichever persistence
 * says it (omnisense/omnibase's plan "omnibase agnostic", step B6): it caught Doctrine's EntityNotFoundException only,
 * so omnibridge/eloquent's EntityNotFound went through. Any other failure still goes through.
 */
final class SettingBagRowGoneTest extends TestCase
{
    protected function setUp(): void
    {
        Persistences::register('row-gone', 'acme/row-gone', 'Acme\RowGone\Marker', null, SettingRowGone::class);
    }

    protected function tearDown(): void
    {
        Persistences::unregister('row-gone');
    }

    private function bag(Settings $settings): SettingBag
    {
        $parameters = $this->createStub(ParameterBagInterface::class);
        $parameters->method('get')->willReturnCallback(static fn (string $name): mixed => 'base.parameter_bag.use_setting_bag' === $name ? true : null);

        return new SettingBag($parameters, $this->createStub(UnitOfWork::class), $settings, $this->createStub(LocalizerInterface::class), $this->createStub(CacheItemPoolInterface::class), 'test');
    }

    public function testACachedSettingWhoseRowIsGoneIsReadAgainWithoutTheCache(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->expects(self::exactly(2))->method('under')
            ->willReturnCallback(static function (?string $path, bool $linkedBagOnly, bool $cached): array {
                if ($cached) {
                    throw new SettingRowGone('the cached setting\'s row is gone');
                }

                return [];
            });

        $empty = $this->createStub(Settings::class);
        $empty->method('under')->willReturn([]);
        // What a path without any setting reads, its row gone or not.
        self::assertSame($this->bag($empty)->getRaw('app.anything'), $this->bag($settings)->getRaw('app.anything'));
    }

    public function testAnotherFailureGoesThrough(): void
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('under')->willThrowException(new \LogicException('not a missing row'));

        $this->expectException(\LogicException::class);
        $this->bag($settings)->getRaw('app.anything');
    }
}
