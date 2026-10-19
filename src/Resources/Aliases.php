<?php

/*
 * The former names of what moved from omniscient/omnibase to omniscient/omnibridge,
 * the omnibridge family's core (plan "omnibase agnostique", A2): the host
 * contracts generic to any framework, the registry's clock and its exception.
 * Each is the moved class under its former name - Omnibridge\Runtime::alias(), a
 * class_alias and the registry told, not a subclass: the same interface, the same
 * reflection name, one service in the registry whichever name it is asked by or
 * bound under.
 *
 * Declared here, when Composer's autoloader is loaded (composer.json,
 * autoload.files), not on demand: PHP autoloads nothing for a parameter's type or
 * an instanceof, so a former name must exist before anything checks against it.
 * The file of each former name (src/Contract/Urls.php...) requires this one, for
 * an autoloader that reads omnibase's src/ without its files (an application that
 * maps Base\ by hand). Base\Contract\Runtime is no alias: omnibase's registry
 * over omnibridge's, with omnibase's own accessors.
 *
 * To be removed at omnibase 4's final pass (~/.claude/plans/omnibase-4-passe-finale.md),
 * once the sites name the new classes.
 */

foreach ([
    'Base\Contract\Urls' => Omnibridge\Contract\Urls::class,
    'Base\Contract\Translations' => Omnibridge\Contract\Translations::class,
    'Base\Contract\Locales' => Omnibridge\Contract\Locales::class,
    'Base\Contract\Access' => Omnibridge\Contract\Access::class,
    'Base\Contract\Sessions' => Omnibridge\Contract\Sessions::class,
    'Base\Contract\Tokens' => Omnibridge\Contract\Tokens::class,
    'Base\Contract\Files' => Omnibridge\Contract\Files::class,
    'Base\Contract\Routes' => Omnibridge\Contract\Routes::class,
    'Base\Contract\Environment' => Omnibridge\Contract\Environment::class,
    'Base\Contract\SystemClock' => Omnibridge\SystemClock::class,
    'Base\Exception\MissingContractException' => Omnibridge\Exception\MissingContractException::class,
] as $former => $class) {
    Omnibridge\Runtime::alias($class, $former);
}
