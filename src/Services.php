<?php

namespace Base;

use Base\Auth\Registration;
use Base\Database\Entity\EntityHydrator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Form\Definition\FormProcessor;
use Base\Notifier\Mail\EmailComposer;
use Base\Screen\Builder\Stories;
use Base\Service\BaseService;
use Base\Service\MediaService;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\Obfuscator\Compression\DeflateCompression;
use Base\Service\Model\Obfuscator\Compression\GzipCompression;
use Base\Service\Model\Obfuscator\Compression\HashidsCompression;
use Base\Service\Model\Obfuscator\Compression\NullCompression;
use Base\Service\Model\Obfuscator\Compression\ZlibCompression;
use Base\Service\Obfuscator;
use Base\Service\ObfuscatorInterface;
use Base\Service\Paginator;
use Base\Service\PaginatorInterface;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;

/**
 * The core's services as data (plan "omnibase agnostic", step F5a; Marco's
 * answer 3: the services stay available everywhere): one list that Symfony's
 * container, Laravel's and omnisense/omnibridge's plain-PHP Omnibridge\Container
 * read alike, so that no service is wired three times.
 *
 * Each definition is a class, its service id (the class by default), the
 * contracts it answers (interfaces and former ids, aliased to it) and its
 * arguments by name - '@id' a service, '%name%' a parameter (Omnibridge\Container's
 * syntax, which omnibridge/laravel reads as well). Whatever a constructor
 * type-hints and the list does not give is autowired by the host: the host's
 * own contracts (Omnibridge\Contract\Urls, Views, Environment, Translations...,
 * Base\Service\ParameterBagInterface, Base\Contract\MediaRouter, Base\Service\Storages)
 * are the host's to give - its bridge under Symfony and Laravel, by hand in
 * plain PHP (docs/20-architecture/services.md).
 *
 * The services named by an id below that the list does not define are the
 * host's too: `imagine.bitmap` and `imagine.svg` (Imagine's ImagineInterface,
 * Imagick's and the SVG one), `Symfony\Component\Translation\Translator` (the
 * catalogues the core's Translator decorates).
 *
 * The list grows service by service as each is written on contracts; under
 * Symfony the core's configuration still wires them (config/services/*.php,
 * omnibridge/symfony's at F5c) and aliases the contracts listed here that it
 * does not alias itself; tests/ServicesTest checks that both agree.
 */
final class Services
{
    /**
     * @return list<array{class: class-string, id?: string, contracts?: list<string>, arguments?: array<string, mixed>}>
     */
    public static function definitions(): array
    {
        $compressions = [NullCompression::class, ZlibCompression::class, GzipCompression::class, DeflateCompression::class, HashidsCompression::class];

        return [
            // The obfuscator: its compressions, the identifiers' map in its cache folder.
            ['class' => NullCompression::class],
            ['class' => ZlibCompression::class],
            ['class' => GzipCompression::class],
            ['class' => DeflateCompression::class],
            ['class' => HashidsCompression::class, 'arguments' => ['$secret' => '%kernel.secret%']],
            ['class' => Obfuscator::class, 'contracts' => [ObfuscatorInterface::class, 'obfuscator'], 'arguments' => [
                '$cacheDir' => '%base.obfuscator.cache_dir%',
                '$compressions' => array_map(static fn (string $class): string => '@'.$class, $compressions),
            ]],
            // The media rule: addresses, thumbnails, the stored files, the media tags (through Views).
            ['class' => MediaService::class, 'contracts' => [MediaServiceInterface::class, 'base.service.image'], 'arguments' => [
                '$imagineBitmap' => '@imagine.bitmap',
                '$imagineSvg' => '@imagine.svg',
            ]],
            // The pages of a list, their addresses through Urls (Symfony's router under Symfony).
            ['class' => Paginator::class, 'contracts' => [PaginatorInterface::class, 'base.paginator']],
            // The catalogues' words, the site's politeness.
            ['class' => Translator::class, 'contracts' => [TranslatorInterface::class, 'base.translator'], 'arguments' => [
                '$translator' => '@Symfony\Component\Translation\Translator',
                '$politeness' => '%base.translator.politeness%',
            ]],
            // A thread's screens (the cards and the stories of a list).
            ['class' => Stories::class],
            // omnibase's e-mails, written through Views the same way under every host; the sender the technical address.
            ['class' => EmailComposer::class, 'arguments' => ['$from' => '%base.notifier.technical_recipient.email%']],
            // The neutral form layer's processing (bind, token, validation, unit of work): each contract optional.
            ['class' => FormProcessor::class],
            // A sign-up once its form holds: approved, its password, named, kept (the host signs it in).
            ['class' => Registration::class],
            // An entity filled from data and rebuilt as it was: on the unit of work, its persistence's shapes and snapshots.
            ['class' => EntityHydrator::class, 'contracts' => [EntityHydratorInterface::class, 'base.database.entity_hydrator']],
            // The facade of the services for an application's code, on the contracts (the host's own half, a subclass:
            // Symfony's is omnibridge/symfony's SymfonyBaseService, which the Symfony configuration defines here).
            ['class' => BaseService::class, 'contracts' => ['base.service']],
        ];
    }
}
