<?php

namespace Base\Contract;

/*
 * What Base\Security\WebauthnUserEntityRepository declares to
 * web-auth/webauthn-symfony-bundle (plan "omniauth", AU1; the pattern of
 * Base\Contract\SecurityUserLoader, R2): with the bundle installed,
 * Base\Bridge\Symfony\PasskeyUsers - its
 * PublicKeyCredentialUserEntityRepositoryInterface, the user repository a
 * site's webauthn.yaml names -, under this name; elsewhere (see
 * Base\Contract\Framework) an interface that asks nothing, and the class
 * loads without the bundle.
 */
if (Framework::declares('Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface')) {
    class_alias(\Base\Bridge\Symfony\PasskeyUsers::class, PasskeyUsers::class);
} else {
    /** The accounts as passkeys know them, to a host without WebauthnBundle. */
    interface PasskeyUsers
    {
    }
}
