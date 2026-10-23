<?php

/*
 * PHPStan's view of the App\ classes the core names (App\Entity\User,
 * App\Repository\...): the core alone is an application that defines none of
 * them, so each is the Base\ class under another name - ClassAliases'
 * convention, the autoloader the runtime registers. PHPStan follows a
 * class_alias() made while it runs: App\X is Base\X itself (an alias, not a
 * subclass: master plan §5, invariant 8), not an unknown class.
 */

Base\Service\Model\ClassAliases::register();

/*
 * The core's seams (Base\Contract\SecurityUser, TwoFactorUser, the user loader
 * and provider, the passkey stores): each file declares, when the framework's
 * interfaces are there, a class_alias to its Base\Bridge\Symfony face, else a
 * bare interface of its own. Loaded here as the runtime loads them, so PHPStan
 * reads the face the package's own install gives (Symfony's security is in its
 * require-dev), not the first declaration it meets in the file.
 */
foreach (['SecurityUser', 'TwoFactorUser', 'SecurityUserLoader', 'SecurityUserProvider', 'PasskeyRecords', 'PasskeyUsers'] as $seam) {
    interface_exists('Base\\Contract\\'.$seam);
}
