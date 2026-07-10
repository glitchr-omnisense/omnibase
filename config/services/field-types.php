<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * This file is part of the Glitchr package.
 *
 * (c) Marco Meyer <marco.meyer@glitchr.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Reference;

/*
 * Form-type services for the Base\Field\Type layer. Extracted verbatim from
 * the former services/admin.php: the Types are plain Symfony form types used
 * by base-bundle-admin and regular forms alike, so they stay registered here
 * after the EA-era admin layer moved to glitchr/base-bundle-admin.
 */
return function (ContainerConfigurator $configurator) {

    $services = $configurator->services();
        $services->defaults()
        ->autowire(false)
        ->autoconfigure(false)
        ->public(false);

    $services->set('Base\Field\Type\PasswordType')
        ->tag('form.type')
        ->args([new Reference('translator'), new Reference('twig')]);
    $services->set('Base\Field\Type\TranslationType')
        ->tag('form.type')
        ->args([
            new Reference('base.database.metadata_manipulator'),
            new Reference('localizer'),
            new Reference('twig'),
        ]);
    $services->set('Base\Field\Type\BooleanType')->tag('form.type')->args([new Reference('twig')]);
    $services->set('Base\Field\Type\ButtonType')->tag('form.type')->args([new Reference('twig')]);
    $services->set('Base\Field\Type\RouteType')
        ->tag('form.type')
        ->args([new Reference('advanced_router'), new Reference('localizer')]);
    $services->set('Base\Field\Type\SelectType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('translator'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('security.csrf.token_manager'),
            new Reference('localizer'),
            new Reference('Base\Admin\Router\AdminUrlGenerator'),
            new Reference('twig'),
            new Reference('security.authorization_checker'),
            new Reference('obfuscator'),
            new Reference('parameter_bag'),
            new Reference('advanced_router'),
        ]);
    $services->set('Base\Field\Type\ForexType')->parent('Base\Field\Type\SelectType')->tag('form.type');
    $services->set('Base\Field\Type\IconType')->parent('Base\Field\Type\SelectType')->tag('form.type')
        ->args([new Reference('base.service.icon')]);
    $services->set('Base\Field\Type\CountryType')->parent('Base\Field\Type\SelectType')->tag('form.type');
    $services->set('Base\Field\Type\AssociationType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.database.entity_hydrator'),
            new Reference('translator'),
        ]);
    $services->set('Base\Field\Type\AssociationFileType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.database.entity_hydrator'),
            new Reference('base.service.image'),
        ]);
    $services->set('Base\Field\Type\AttributeType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('twig'),
        ]);
    $services->set('Base\Field\Type\DiscriminatorType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
        ]);
    $services->set('Base\Field\Type\WysiwygType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('twig'),
        ]);
    $services->set('Base\Field\Type\EditorType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('twig'),
            new Reference('advanced_router'),
            new Reference('security.csrf.token_manager'),
            new Reference('obfuscator'),
            new Reference('media_enhancer'),
        ]);
    $services->set('Base\Field\Type\MoneyType')
        ->tag('form.type')
        ->args([new Reference('trading_market')]);
    $services->set('Base\Field\Type\NumberType')
        ->tag('form.type')
        ->args([new Reference('twig')]);
    $services->set('Base\Field\Type\StockType')
        ->tag('form.type');
    $services->set('Base\Field\Type\DateTimePickerType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('twig'),
            new Reference('localizer'),
        ]);
    $services->set('Base\Field\Type\ColorType')
        ->tag('form.type')
        ->args([
            new Reference('twig'),
            new Reference('parameter_bag'),
        ]);
    $services->set('Base\Field\Type\ColorPickerType')
        ->tag('form.type')
        ->args([new Reference('translator')]);
    $services->set('Base\Field\Type\EmojiPickerType')
        ->tag('form.type');
    $services->set('Base\Field\Type\SlugType')
        ->tag('form.type')
        ->args([
            new Reference('twig'),
            new Reference('base.database.metadata_manipulator'),
        ]);
    $services->set('Base\Field\Type\QuadrantType')
        ->tag('form.type')
        ->args([new Reference('twig')]);
    $services->set('Base\Field\Type\CropperType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('parameter_bag'),
            new Reference('twig'),
        ]);
    $services->set('Base\Field\Type\FileType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('twig'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('security.csrf.token_manager'),
            new Reference('form.factory'),
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('obfuscator'),
            '%kernel.cache_dir%',
        ]);
    $services->set('Base\Field\Type\ImageType')
        ->parent('Base\Field\Type\FileType')
        ->tag('form.type');
    $services->set('Base\Field\Type\AudioType')
        ->parent('Base\Field\Type\FileType')
        ->tag('form.type');
    $services->set('Base\Field\Type\VideoType')
        ->parent('Base\Field\Type\FileType')
        ->tag('form.type');
    $services->set('Base\Field\Type\AvatarType')
        ->parent('Base\Field\Type\ImageType')
        ->tag('form.type');
    $services->set('Base\Field\Type\CollectionType')
        ->tag('form.type')
        ->args([
            new Reference('twig'),
            new Reference('translator'),
            new Reference('security.authorization_checker'),
            new Reference('Base\Admin\Router\AdminUrlGenerator'),
        ]);
    $services->set('Base\Field\Type\ArrayType')
        ->parent('Base\Field\Type\CollectionType')
        ->tag('form.type')
        ->args([new Reference('base.database.metadata_manipulator')]);
};
