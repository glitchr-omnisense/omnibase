<?php

namespace Tests\Base\Security\Voter;

use Base\Security\Voter\PermissionVoter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * supports()'s prefix exclusions are load-bearing: this voter must never
 * claim ROLE_, IS_ or EA_ prefixed attributes, or it could change existing
 * role-hierarchy/EasyAdmin-style authorization behavior. Tested via
 * reflection on the protected method directly - no token/subject needed
 * for this part, and it keeps the test a fast, dependency-free unit test.
 */
class PermissionVoterTest extends TestCase
{
    private function supports(string $attribute): bool
    {
        $method = new ReflectionMethod(PermissionVoter::class, 'supports');
        $method->setAccessible(true);

        return $method->invoke(new PermissionVoter(), $attribute, null);
    }

    public function testDoesNotClaimRolePrefixedAttributes(): void
    {
        $this->assertFalse($this->supports('ROLE_ADMIN'));
        $this->assertFalse($this->supports('ROLE_SUPERADMIN'));
    }

    public function testDoesNotClaimIsPrefixedAttributes(): void
    {
        $this->assertFalse($this->supports('IS_AUTHENTICATED_FULLY'));
    }

    public function testDoesNotClaimEaPrefixedAttributes(): void
    {
        $this->assertFalse($this->supports('EA_VIEW_MENU_ITEM'));
        $this->assertFalse($this->supports('EA_EXECUTE_ACTION'));
    }

    public function testClaimsCustomPermissionNames(): void
    {
        $this->assertTrue($this->supports('ARTICLE.PUBLISH'));
        $this->assertTrue($this->supports('CONTENT_MANAGER'));
    }

    /**
     * A ".*" suffix is only meaningful as a stored Permission tag (checked
     * against in voteOnAttribute()'s wildcard match), never as the attribute
     * being voted on itself - "*" is deliberately outside the allowed
     * attribute charset.
     */
    public function testWildcardSuffixIsNotAValidAttributeToVoteOn(): void
    {
        $this->assertFalse($this->supports('ARTICLE.*'));
    }

    public function testDoesNotClaimLowercaseOrMalformedAttributes(): void
    {
        $this->assertFalse($this->supports('article.publish'));
        $this->assertFalse($this->supports(''));
    }
}
