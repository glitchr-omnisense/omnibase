<?php

namespace Base\Repository\User;

use Base\Entity\User\Token;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Token|null find($id, $lockMode = null, $lockVersion = null)
 * @method Token|null findOneBy(array $criteria, array $orderBy = null)
 * @method Token[]    findAll()
 * @method Token[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TokenRepository extends ServiceEntityRepository implements Tokens
{
    /** Tokens (step R28): SecurityController's and AccessTokenHandler's finders. */
    public function oneByValueAndName(?string $value, string $name): ?Token
    {
        return $this->findOneByValueAndName($value, $name);
    }

    public function oneByValue(?string $value): ?Token
    {
        return $this->findOneByValue($value);
    }

    public function oneSigningInByNameAndValue(string $name, ?string $value): ?Token
    {
        return $this->findOneByNameAndValueAndIsLogginable($name, $value, true);
    }

    public function oneSigningInByValue(?string $value): ?Token
    {
        return $this->findOneByValueAndIsLogginable($value);
    }

}
