<?php

/*
 * A passkey where neither WebauthnBundle nor the framework is installed (plan
 * "omniauth", AU1): run by docker/bin/bare for tests/BareTest.php, with
 * Composer's autoloader alone. Base\Entity\User\Passkey is made and its
 * columns written and read by the core's types, on a platform of DBAL, as a
 * host without Symfony does; then the classes PHP loaded are looked at: none
 * of webauthn-lib (the entity no longer extends its CredentialRecord), none
 * of the bundle. Returns what it found.
 */

use Base\Bridge\Doctrine\Types;
use Base\Entity\User\Passkey;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Types\Type;

Types::register();
$platform = new MySQL80Platform();

$passkey = new Passkey();
$passkey->publicKeyCredentialId = "\x01\x02\xff";
$passkey->credentialPublicKey = "\xa5\x01\x02";
$passkey->userHandle = '42';
$passkey->trustPath = ['x5c' => ['MIIB/w==']];

$row = [];
foreach (['publicKeyCredentialId' => 'passkey_base64', 'credentialPublicKey' => 'passkey_base64', 'aaguid' => 'passkey_aaguid', 'trustPath' => 'passkey_trust_path'] as $field => $type) {
    $row[$field] = Type::getType($type)->convertToDatabaseValue($passkey->{$field}, $platform);
}
$read = Type::getType('passkey_base64')->convertToPHPValue($row['publicKeyCredentialId'], $platform);

$webauthn = array_values(array_filter(
    array_merge(get_declared_classes(), get_declared_interfaces()),
    static fn (string $class) => str_starts_with($class, 'Webauthn\\'),
));

return [
    'parent' => get_parent_class($passkey),
    'row' => $row,
    'read back' => $read === $passkey->publicKeyCredentialId,
    'webauthn loaded' => $webauthn,
];
