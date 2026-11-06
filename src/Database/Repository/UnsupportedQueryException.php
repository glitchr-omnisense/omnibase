<?php

namespace Base\Database\Repository;

/**
 * A repository method the persistence installed does not run: declared #[BackendOnly] for
 * another one (plan "omnibase agnostic", step E2), refused aloud rather than answered wrong.
 */
final class UnsupportedQueryException extends \LogicException
{
}
