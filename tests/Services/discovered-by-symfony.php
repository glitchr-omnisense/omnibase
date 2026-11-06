<?php

/*
 * What Symfony's container discovered among the core's services on lesringostars (its compiled container before
 * step F5e slice 4c, ~/Sites/.agent-briefs/f5e-s4/di-before): the calls each collecting service of Base\Discovery's
 * `collect` received, the core's services only and in their order, and the core's enumerations. BareTest compares
 * what Omnibridge\Container discovers with it. Written by f5e-s4c/tools/discovered-by-symfony.py.
 */

return [
    'collect' => [
        'Base\\Attributes\\AttributeReader' => [],
        'Base\\Database\\Entity\\EntityExtension' => [],
        'Base\\Service\\IconProvider' => ['Base\\Service\\Model\\IconProvider\\Adapter\\FontAwesomeAdapter', 'Base\\Service\\Model\\IconProvider\\Adapter\\BootstrapTwitterAdapter'],
        'Base\\Service\\Obfuscator' => ['Base\\Service\\Model\\Obfuscator\\Compression\\NullCompression', 'Base\\Service\\Model\\Obfuscator\\Compression\\ZlibCompression', 'Base\\Service\\Model\\Obfuscator\\Compression\\GzipCompression', 'Base\\Service\\Model\\Obfuscator\\Compression\\DeflateCompression', 'Base\\Service\\Model\\Obfuscator\\Compression\\HashidsCompression'],
        'Base\\Service\\Sharing' => ['Base\\Service\\Model\\Sharing\\Adapter\\FacebookAdapter', 'Base\\Service\\Model\\Sharing\\Adapter\\LinkedInAdapter', 'Base\\Service\\Model\\Sharing\\Adapter\\GooglePlusAdapter', 'Base\\Service\\Model\\Sharing\\Adapter\\TumblrAdapter', 'Base\\Service\\Model\\Sharing\\Adapter\\TwitterAdapter', 'Base\\Service\\Model\\Sharing\\Adapter\\PinterestAdapter'],
        'Base\\Service\\Trading' => ['Base\\Service\\Model\\Currency\\Api\\Fixer', 'Base\\Service\\Model\\Currency\\Api\\AbstractApi', 'Base\\Service\\Model\\Currency\\Api\\ExchangeRatesApi', 'Base\\Service\\Model\\Currency\\Api\\CurrencyLayer'],
        'Base\\Twig\\Environment' => ['Base\\Twig\\Renderer\\Adapter\\HtmlTagRenderer', 'Base\\Twig\\Renderer\\Adapter\\WebpackTagRenderer'],
    ],
    'enumerations' => [
        'spam_api' => 'Base\\Enum\\SpamApi',
        'log_level' => 'Base\\Enum\\LogLevel',
        'user_state' => 'Base\\Enum\\UserState',
        'spam_score' => 'Base\\Enum\\SpamScore',
        'thread_state' => 'Base\\Enum\\ThreadState',
        'upload_state' => 'Base\\Enum\\UploadState',
        'user_gender' => 'Base\\Enum\\UserGender',
        'workflow_state' => 'Base\\Enum\\WorkflowState',
        'quadrant32' => 'Base\\Enum\\Quadrant\\Quadrant32',
        'quadrant' => 'Base\\Enum\\Quadrant\\Quadrant',
        'quadrant16' => 'Base\\Enum\\Quadrant\\Quadrant16',
        'quadrant8' => 'Base\\Enum\\Quadrant\\Quadrant8',
        'entity_action' => 'Base\\Enum\\EntityAction',
        'operation' => 'Base\\Enum\\Operation',
        'user_role' => 'Base\\Enum\\UserRole',
        'connection_state' => 'Base\\Enum\\ConnectionState',
        'standard_base_units' => 'Base\\Enum\\SystemOfUnits\\StandardBaseUnits',
        'united_states_customary_units' => 'Base\\Enum\\SystemOfUnits\\UnitedStatesCustomaryUnits',
        'british_imperial_units' => 'Base\\Enum\\SystemOfUnits\\BritishImperialUnits',
        'standard_units' => 'Base\\Enum\\SystemOfUnits\\StandardUnits',
    ],
];

