<?php

namespace Base\Contract;

/*
 * What Base\Repository\User\PasskeyRepository declares to
 * web-auth/webauthn-symfony-bundle (plan "omniauth", AU1; the pattern of
 * Base\Contract\SecurityUserLoader, R2): with the bundle installed,
 * Base\Bridge\Symfony\PasskeyRecords - its CredentialRecordRepositoryInterface
 * and CanSaveCredentialRecord, the credential repository a site's
 * webauthn.yaml names -, under this name; elsewhere (see
 * Base\Contract\Framework) an interface that asks nothing, and the repository
 * loads without the bundle. Its methods are the same either way; their
 * signatures name webauthn-lib's types, which PHP does not load to declare
 * them.
 */
if (Framework::declares(
    'Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface',
    'Webauthn\Bundle\Repository\CanSaveCredentialRecord',
)) {
    class_alias(\Base\Bridge\Symfony\PasskeyRecords::class, PasskeyRecords::class);
} else {
    /** The passkeys' repository, to a host without WebauthnBundle. */
    interface PasskeyRecords
    {
    }
}
