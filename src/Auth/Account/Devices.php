<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Repository\User\Connections;
use Base\Repository\User\Passkeys;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;

/**
 * The account's sessions and passkeys on its settings page, written once for every host (plan "omnibase agnostic",
 * step F5e, slice 5b; formerly UserSettingsController::Settings(), RenamePasskey(), DeletePasskey() and
 * LogoutSession()): the twenty sessions last used (the list is there to spot one that should not be, the oldest of
 * six hundred tell nobody anything), a passkey renamed or removed and a session signed out - each by its token, and
 * only the account's own (another's: no such page) -, then back to the settings.
 */
final class Devices
{
    /** How many of the account's sessions the settings page lists. */
    public const SESSIONS_SHOWN = 20;
    public const SETTINGS = 'user_settings';
    public const SESSION_TOKEN = 'session_logout';

    public function __construct(
        private readonly Access $access,
        private readonly Connections $connections,
        private readonly Passkeys $passkeys,
        private readonly ?Tokens $tokens = null,
    ) {
    }

    /** @return list<object> the account's sessions, the last used first */
    public function sessions(): array
    {
        $user = $this->access->getUser();

        return array_values($this->connections->findBy(['user' => $user], ['updatedAt' => 'DESC'], self::SESSIONS_SHOWN));
    }

    public static function passkeyToken(string $action, int $id): string
    {
        return 'passkey_'.$action.'_'.$id;
    }

    /** A passkey's label (none for an empty one). */
    public function renamePasskey(int $id, ?string $token, ?string $label): Outcome
    {
        $passkey = $this->own($this->passkeys, $id, self::passkeyToken('rename', $id), $token);
        if ($passkey instanceof Outcome) {
            return $passkey;
        }
        $passkey->setLabel(trim((string) $label) ?: null);
        $this->passkeys->flush();

        return Outcome::route(self::SETTINGS);
    }

    public function deletePasskey(int $id, ?string $token): Outcome
    {
        $passkey = $this->own($this->passkeys, $id, self::passkeyToken('delete', $id), $token);
        if ($passkey instanceof Outcome) {
            return $passkey;
        }
        $this->passkeys->remove($passkey, true);
        (new Notification('@notifications.settings.passkeyDeleted'))->send('success');

        return Outcome::route(self::SETTINGS);
    }

    /** One of the account's sessions signed out. */
    public function signOut(int $id, ?string $token): Outcome
    {
        $connection = $this->own($this->connections, $id, self::SESSION_TOKEN, $token);
        if ($connection instanceof Outcome) {
            return $connection;
        }
        $connection->markAsLogout();
        $this->connections->flush();

        return Outcome::route(self::SETTINGS);
    }

    /** The account's own record of that id, by the token of $intent: a refusal (403), no such page (404) otherwise. */
    private function own(Passkeys|Connections $records, int $id, string $intent, ?string $token): object
    {
        if (!$this->tokens?->isValid($intent, $token)) {
            return Outcome::denied();
        }
        $user = $this->access->getUser();
        $record = $records->find($id);

        return null !== $record && $user instanceof User && $record->getUser() === $user ? $record : Outcome::missing();
    }
}
