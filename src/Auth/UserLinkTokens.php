<?php

namespace Base\Auth;

use Base\Entity\User\Token;
use Omniauth\Contract\LinkTokens;
use Omniauth\Contract\PlainLinkTokens;

/**
 * The sign-in links: the userToken rows named "login-token", logginable, as
 * SecurityController::LoginTokenRequest made them - now, by Marco's decision
 * QA4, holding the link's SHA-256 (64 hexadecimal characters) in `value`,
 * never the link, until the moment omniauth gives (15 minutes), and used up
 * at the first sign-in: claimed by marking it revoked (one UPDATE: of two
 * requests with the same link, one only gets the account), then removed.
 *
 * A new link takes the place of the account's former one (User::addToken()
 * keeps one token per name). The links sent before the switch kept their
 * value in clear (22 characters of base64): consumePlain() reads them as they
 * are until their own deadline - never a value shaped like a hash, so the
 * text of the column is no link either.
 */
class UserLinkTokens extends UserStore implements LinkTokens, PlainLinkTokens
{
    public const NAME = 'login-token';

    public function issue(object $account, string $hash, \DateTimeImmutable $until): void
    {
        $user = $this->user($account);
        $user->removeExpiredTokens();

        // No throttle of the token's own: omniauth limits the requests ("link.begin").
        $token = new Token(self::NAME, max(1, $until->getTimestamp() - time()), 0);
        $token->setValue($hash);
        $token->setExpiry(\DateTime::createFromImmutable($until));
        $token->markAsLogginable();
        $user->addToken($token);

        $this->entityManager->persist($token);
        $this->save();
    }

    public function consume(string $hash, \DateTimeImmutable $now): ?object
    {
        return 1 === preg_match('~^[0-9a-f]{64}$~', $hash) ? $this->take($hash, $now) : null;
    }

    public function consumePlain(string $value, \DateTimeImmutable $now): ?object
    {
        return '' !== $value && 1 !== preg_match('~^[0-9a-f]{64}$~i', $value) ? $this->take($value, $now) : null;
    }

    private function take(string $value, \DateTimeImmutable $now): ?object
    {
        $token = $this->entityManager->getRepository(Token::class)->findOneBy(['name' => self::NAME, 'value' => $value, 'isLogginable' => true]);
        if (!$token instanceof Token || $token->isRevoked()) {
            return null;
        }
        $user = $token->getUser();
        $until = $token->getExpiry();
        if (null === $user || null === $until || $until->getTimestamp() <= $now->getTimestamp()) {
            return null;
        }

        $claimed = $this->entityManager->createQuery('UPDATE '.$this->entityManager->getClassMetadata($token::class)->getName().' t SET t.isRevoked = true WHERE t.id = :id AND t.isRevoked = false')
            ->setParameter('id', $token->getId())
            ->execute();
        if (1 !== $claimed) {
            return null;
        }
        $token->markAsRevoked();
        $this->entityManager->remove($token);
        $this->save();

        return $user;
    }
}
