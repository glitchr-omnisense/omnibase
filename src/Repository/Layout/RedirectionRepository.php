<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Database\Repository\Finder\Change;
use Base\Database\Type\Utc;
use Base\Entity\Layout\Redirection;
use Base\Repository\Layout\Redirections;

/**
 * @method Redirection|null find($id)
 * @method Redirection[]    findAll()
 */
class RedirectionRepository extends ServiceEntityRepository implements Redirections
{
    /**
     * The redirection for an address (Redirection::normalize()): the one
     * written for it with its query, then for its path alone, then the longest
     * prefix ("/a/b/*" before "/a/*") that covers it.
     */
    public function forAddress(string $address): ?Redirection
    {
        $path = strtok($address, '?') ?: '/';
        $candidates = array_values(array_unique([$address, $path]));

        /** @var Redirection[] $exact */
        $exact = $this->findBy(['enabled' => true, 'source' => $candidates]);
        foreach ($candidates as $candidate) {
            foreach ($exact as $redirection) {
                if ($redirection->getSource() === $candidate) {
                    return $redirection;
                }
            }
        }

        // The database compared with its collation - MySQL's default takes
        // "/Produit/Enseigne" for "/produit/enseigne" - and the comparison
        // above, letter for letter, then threw away the row it had found: the
        // old address answered 404. What the database found for the address
        // is the redirection, whatever the case; the same letters still win.
        foreach ($candidates as $candidate) {
            foreach ($exact as $redirection) {
                if (mb_strtolower($redirection->getSource()) === mb_strtolower($candidate)) {
                    return $redirection;
                }
            }
        }

        /** @var Redirection[] $prefixes */
        $prefixes = $this->findByEnabledTrueAndSourceEndingWith('*')->getResult();
        usort($prefixes, static fn (Redirection $a, Redirection $b) => \strlen($b->getSource()) <=> \strlen($a->getSource()));
        foreach ($prefixes as $redirection) {
            if (null !== $redirection->resolve($address)) {
                return $redirection;
            }
        }

        return null;
    }

    /** One more visitor carried: counted in the database, whatever else the request holds. */
    public function hit(Redirection $redirection): void
    {
        $this->updateBy(['id' => $redirection->getId()], ['hits' => Change::add(1), 'lastHitAt' => Utc::now()]);
    }
}
