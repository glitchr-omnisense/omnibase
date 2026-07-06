<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use Base\Field\Configurator\LocaleConfigurator;
use Base\Field\Configurator\TextConfigurator;
use Base\Field\Configurator\WysiwygConfigurator;
use Base\Field\Configurator\StockConfigurator;
use Base\Field\Configurator\MoneyConfigurator;
use Base\Field\Configurator\CurrencyConfigurator;
use Base\Field\Configurator\EmailConfigurator;
use Base\Field\Configurator\DateTimePickerConfigurator;
use Base\Field\Configurator\CollectionConfigurator;
use Base\Field\Configurator\ArrayConfigurator;
use Base\Field\Configurator\SlugConfigurator;
use Base\Field\Configurator\CropperConfigurator;
use Base\Field\Configurator\FileConfigurator;
use Base\Field\Configurator\TranslationConfigurator;
use Base\Field\Configurator\BooleanConfigurator;
use Base\Field\Configurator\AssociationConfigurator;
use Base\Field\Configurator\AssociationFileConfigurator;
use Base\Field\Configurator\SelectConfigurator;
use Base\Field\Configurator\RoleConfigurator;
use Base\Field\Configurator\StateConfigurator;
use Base\Field\Configurator\CountryConfigurator;
use Base\Field\Configurator\QuadrantConfigurator;
use Base\Field\Configurator\DiscriminatorConfigurator;
use Base\Field\Configurator\AttributeConfigurator;
use Base\Field\Configurator\IconConfigurator;
use EasyCorp\Bundle\EasyAdminBundle\Intl\IntlFormatter;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // Twig services
    $services->set('Base\Twig\AssetPackage')->public()
        ->tag('assets.package', ['package' => 'base.assets.package'])
        ->args([new Reference('request_stack')]);

    $services->set('Base\Twig\Variable\SiteVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('advanced_router'),
            new Reference('sitemap'),
            new Reference('translator'),
            new Reference('localizer'),
            new Reference('base.service'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher'),
        ]);

    $services->set('Base\Twig\Variable\EmailVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.service'),
            new Reference('base.service.launcher'),
        ]);

    $services->set('Base\Twig\Variable\RandomVariable')->public()
        ->tag('twig.variable');

    $services->set('Base\Twig\Variable\AdminVariable')->public()
        ->parent('Base\Twig\Variable\SiteVariable')
        ->tag('twig.runtime')
        ->tag('twig.variable')
        ->args([new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')]);

    $services->set('Base\Twig\Extension\MediaTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('Base\Controller\UX\MediaController'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    $services->set('Base\Twig\Extension\SemanticTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('semantic_enhancer')]);

    $services->set('Base\Twig\Extension\TradingTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('trading_market')]);

    // Twig renderer adapters
    $services->set('Base\Twig\Renderer\AbstractTagRenderer')
        ->abstract()
        ->args([
            new Reference('twig'),
            new Reference('localizer'),
            new Reference('slugger'),
            new Reference('parameter_bag'),
        ]);

    $services->set('Base\Twig\Renderer\Adapter\HtmlTagRenderer')->public()
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router'),
        ]);

    $services->set('Base\Twig\Renderer\Adapter\WebpackTagRenderer')->public()
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('webpack_encore.entrypoint_lookup_collection', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('assets.packages'),
        ])
        ->bind('$publicDir', '%kernel.project_dir%/public')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Twig\Extension\WebpackTwigExtension')
        ->tag('twig.extension', ['priority' => -1]);

    $services->set('Base\Twig\Extension\HtmlTwigExtension')
        ->tag('twig.extension')
        ->args([
            new Reference('twig.html_renderer'),
            new Reference('wysiwyg_enhancer'),
            new Reference('editor_enhancer'),
        ]);

    $services->set('Base\Twig\Extension\FunctionTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('translator'),
            new Reference('twig.extension.assets'),
            new Reference('twig'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    $services->set('Base\Twig\Extension\MathTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\ClassTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\ClipboardTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\FormTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('form.proxy')]);
    $services->set('Base\Twig\Extension\WidgetTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.widget_provider')]);
    $services->set('Base\Twig\Extension\LocalizerTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('localizer')]);
    $services->set('Base\Twig\Extension\ThemizerTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('themizer')]);
    $services->set('Base\Twig\Extension\TranslatorTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('localizer')]);
    $services->set('Base\Twig\Extension\PaginatorTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('translator')]);
    $services->set('Base\Twig\Extension\BreadgrinderTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.breadgrinder')]);
    $services->set('Base\Twig\Extension\ShareTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.service.sharer')]);

    // Form extensions
    $services->set('Base\Form\Extension\EaCrudFormCompatExtension')->tag('form.type_extension');
    $services->set('Base\Form\Extension\FormTypeBootstrapExtension')
        ->tag('form.type_extension')
        ->args([new Reference('base.service')]);
    $services->set('Base\Form\Extension\FormTypeCsrfExtension')->tag('form.type_extension');
    $services->set('Base\Form\Extension\FormTypeExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('security.authorization_checker'),
            new Reference('parameter_bag'),
            new Reference('form.factory'),
            new Reference('form.proxy'),
            new Reference('base.database.metadata_manipulator'),
        ]);
    $services->set('Base\Form\Extension\FormTypeWebpackExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('form.proxy'),
            new Reference('twig.webpack_renderer'),
        ]);
    $services->set('Base\Form\Extension\FormTypeCollectionExtension')
        ->tag('form.type_extension')
        ->args([new Reference('base.database.metadata_manipulator')]);

    // Validators
    $services->set('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator')
        ->args([new Reference('translator')]);

    $services->set('Base\Validator\Constraints\AlphanumericValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\AlphanumericPlusValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\FileValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\HexcodeValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\NotBlankValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\ConstraintEntityValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\UniqueEntityValidator')
        ->parent('Base\Validator\ConstraintEntityValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\StringCaseEntityValidator')
        ->parent('Base\Validator\ConstraintEntityValidator')
        ->tag('validator.constraint_validator');

$services->set('Base\Database\Mapping\NamingStrategy')->public();
    $services->set('Base\Database\Mapping\ClassMetadataFactory')->public();

    $services->set('Base\Database\Entity\EntityHydrator')
        ->public()
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.database.metadata_manipulator')
        ]);

    $services->set('Base\Database\Mapping\ClassMetadataManipulator')
        ->public()
        ->args([
            new Reference('doctrine'),
            new Reference('doctrine.orm.entity_manager')
        ])
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Attributes\AttributeReader')
        ->public()
        ->args([
            new Reference('event_dispatcher'),
            new Reference('advanced_router'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('parameter_bag'),
            new Reference('flysystem'),
            new Reference('request_stack'),
            new Reference('security.token_storage'),
            new Reference('base.database.entity_hydrator'),
            new Reference('base.database.metadata_manipulator')
        ])
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$environment', '%kernel.environment%')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Security\Voter\AdminVoter')
        ->tag('security.voter')
        ->args([new Reference('advanced_router')]);

    $services->set('Base\Security\Voter\AccessVoter')
        ->tag('security.voter')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router'),
            new Reference('setting_bag'),
            new Reference('parameter_bag'),
            new Reference('security.firewall.map'),
            new Reference('localizer'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher')
        ]);

    $services->set('Base\Database\Entity\EntityExtension')->public();

    // ------------------------------
    // Twig Variables & Extensions
    // ------------------------------
    $services->set('Base\Twig\AssetPackage')->public()
        ->tag('assets.package', ['package' => 'base.assets.package'])
        ->args([new Reference('request_stack')]);

    $services->set('Base\Twig\Variable\SiteVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('advanced_router'),
            new Reference('sitemap'),
            new Reference('translator'),
            new Reference('localizer'),
            new Reference('base.service'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher')
        ]);

    $services->set('Base\Twig\Variable\EmailVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.service'),
            new Reference('base.service.launcher')
        ]);

    $services->set('Base\Twig\Variable\RandomVariable')->public()
        ->tag('twig.variable');

    $services->set('Base\Twig\Variable\AdminVariable')
        ->parent('Base\Twig\Variable\SiteVariable')
        ->public()
        ->tag('twig.runtime')
        ->tag('twig.variable')
        ->args([new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')]);

    $services->set('Base\Twig\Extension\MediaTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('Base\Controller\UX\MediaController')
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    $services->set('Base\Twig\Extension\SemanticTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('semantic_enhancer')]);

    $services->set('Base\Twig\Extension\TradingTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('trading_market')]);

    $services->set('Base\Twig\Renderer\AbstractTagRenderer')
        ->abstract()
        ->args([
            new Reference('twig'),
            new Reference('localizer'),
            new Reference('slugger'),
            new Reference('parameter_bag')
        ]);

    $services->set('Base\Twig\Renderer\Adapter\HtmlTagRenderer')
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->public()
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router')
        ]);

    $services->set('Base\Twig\Renderer\Adapter\WebpackTagRenderer')
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->public()
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('webpack_encore.entrypoint_lookup_collection', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('assets.packages')
        ])
        ->bind('$publicDir', '%kernel.project_dir%/public')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Twig\Extension\WebpackTwigExtension')
        ->tag('twig.extension', ['priority' => -1]);

    $services->set('Base\Twig\Extension\HtmlTwigExtension')
        ->tag('twig.extension')
        ->args([
            new Reference('twig.html_renderer'),
            new Reference('wysiwyg_enhancer'),
            new Reference('editor_enhancer')
        ]);

    $services->set('Base\Twig\Extension\FunctionTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('translator'),
            new Reference('twig.extension.assets'),
            new Reference('twig'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')
        ])
        ->bind('$projectDir', '%kernel.project_dir%');
};
