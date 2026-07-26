<?php

namespace Base\Controller\Client;

use Base\Service\BaseService;

use App\Repository\UserRepository;
use Base\Entity\User\Connection;
use Base\Repository\User\ConnectionRepository;
use Base\Repository\User\PasskeyRepository;

use App\Form\Extension\Login2FAType;
use Base\Attributes\Attribute\Iconize;
use Base\Entity\User\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

class UserSettingsController extends AbstractController
{
    private const SESSION_PENDING_SECRET = "2fa_pending_totp_secret";

    private $baseService;
    private UserRepository $userRepository;

    public function __construct(BaseService $baseService, UserRepository $userRepository)
    {
        $this->baseService = $baseService;
        $this->userRepository = $userRepository;
    }

    #[Route("/members/qr/totp", name: "qr_code_totp")]
    public function displayTotpQrCode(TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        $user = $this->getUser();
        if (!($user instanceof TotpTwoFactorInterface) || !$user->isTotpAuthenticationEnabled()) {
            throw new NotFoundHttpException('Cannot display QR code');
        }

        return $this->displayQrCode($totpAuthenticator->getQRContent($user));
    }

    private function displayQrCode(string $qrCodeContent): Response
    {
        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            data: $qrCodeContent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $result = $builder->build();

        return new Response($result->getString(), 200, ['Content-Type' => 'image/png']);
    }

    /**
     * @return string[] plain-text codes (only ever shown once, right after generation)
     */
    private function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
        }
        return $codes;
    }

    #[Route("/settings", name: "user_settings")]
    #[Iconize("fa-solid fa-fw fa-user-cog")]
    public function Settings(ConnectionRepository $connectionRepository)
    {
        $user = $this->getUser();
        $sessions = $connectionRepository->findByUser($user, ["updatedAt" => "DESC"]);

        return $this->render('client/user/settings.html.twig', [
            'user' => $user,
            'sessions' => $sessions,
        ]);
    }

    #[Route("/settings/passkeys/{id}/rename", name: "user_settings_passkey_rename", methods: ["POST"])]
    public function RenamePasskey(int $id, Request $request, PasskeyRepository $passkeyRepository, EntityManagerInterface $entityManager)
    {
        if (!$this->isCsrfTokenValid('passkey_rename_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $passkey = $passkeyRepository->find($id);
        if (!$passkey || $passkey->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        $passkey->setLabel(trim((string) $request->request->get('label')) ?: null);
        $entityManager->flush();

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/passkeys/{id}/delete", name: "user_settings_passkey_delete", methods: ["POST"])]
    public function DeletePasskey(int $id, Request $request, PasskeyRepository $passkeyRepository, EntityManagerInterface $entityManager)
    {
        if (!$this->isCsrfTokenValid('passkey_delete_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $passkey = $passkeyRepository->find($id);
        if (!$passkey || $passkey->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        $entityManager->remove($passkey);
        $entityManager->flush();

        $notification = new Notification("Passkey removed.");
        $notification->send("success");

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/sessions/{id}/logout", name: "user_settings_session_logout", methods: ["POST"])]
    public function LogoutSession(int $id, Request $request, ConnectionRepository $connectionRepository, EntityManagerInterface $entityManager)
    {
        if (!$this->isCsrfTokenValid('session_logout', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        $connection = $connectionRepository->find($id);

        if (!$connection || $connection->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        $connection->markAsLogout();
        $entityManager->flush();

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/2fa", name: "user_settings_2fa")]
    public function TwoFactorAuthentification(Request $request, EntityManagerInterface $entityManager, TotpAuthenticatorInterface $totpAuthenticator)
    {
        $user = $this->getUser();
        $session = $request->getSession();

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('user_settings');
        }

        $pendingSecret = $session->get(self::SESSION_PENDING_SECRET) ?? $totpAuthenticator->generateSecret();
        $session->set(self::SESSION_PENDING_SECRET, $pendingSecret);

        $form = $this->createForm(Login2FAType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Validate against a throwaway clone: the real, tracked $user entity must not carry an
            // unconfirmed secret, since other requests in this app flush() the entity manager as a
            // side effect (e.g. UserTracker::updateConnection()) and would silently persist it.
            $pendingUser = clone $user;
            $pendingUser->setTotpSecret($pendingSecret);

            if ($totpAuthenticator->checkCode($pendingUser, $form->get('code')->getData())) {

                $user->setTotpSecret($pendingSecret);
                $backupCodes = $this->generateBackupCodes();
                $user->setBackupCodes($backupCodes);
                $entityManager->flush();

                $session->remove(self::SESSION_PENDING_SECRET);

                $notification = new Notification("2FA has been enabled on your account.");
                $notification->send("success");

                return $this->render('client/user/settings_2fa_backup_codes.html.twig', [
                    'backupCodes' => $backupCodes,
                ]);
            }

            $form->get('code')->addError(new \Symfony\Component\Form\FormError('Invalid code, please try again.'));
        }

        return $this->render('client/user/settings_2fa.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route("/settings/2fa/disable", name: "user_settings_2fa_disable", methods: ["POST"])]
    public function TwoFactorAuthentification_Disable(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('2fa_disable', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$passwordHasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $notification = new Notification("Incorrect password.");
            $notification->send("danger");
            return $this->redirectToRoute('user_settings');
        }

        $user->setTotpSecret(null);
        $user->setBackupCodes([]);
        $user->invalidateTrustedDevices();
        $entityManager->flush();

        $notification = new Notification("2FA has been disabled on your account.");
        $notification->send("success");

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/2fa/email", name: "user_settings_2fa_email_toggle", methods: ["POST"])]
    public function TwoFactorAuthentification_ToggleEmail(Request $request, EntityManagerInterface $entityManager)
    {
        if (!$this->isCsrfTokenValid('2fa_email', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        $user->setEmailAuthEnabled(!$user->isEmailAuthEnabled());
        $entityManager->flush();

        $notification = new Notification($user->isEmailAuthEnabled() ? "Email verification codes enabled." : "Email verification codes disabled.");
        $notification->send("success");

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/2fa/qr-code", name: "user_settings_2fa_qrcode")]
    public function TwoFactorAuthentification_QrCode(Request $request, TotpAuthenticatorInterface $totpAuthenticator)
    {
        $user = $this->getUser();
        $secret = $request->getSession()->get(self::SESSION_PENDING_SECRET);
        if (!$secret) {
            throw new NotFoundHttpException('No pending 2FA setup');
        }

        $pendingUser = clone $user;
        $pendingUser->setTotpSecret($secret);

        return $this->displayQrCode($totpAuthenticator->getQRContent($pendingUser));
    }
}
