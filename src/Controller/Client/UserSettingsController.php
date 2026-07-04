<?php

namespace Base\Controller\Client;

use Base\Service\BaseService;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Form\Extension\Login2FAType;
use Base\Attributes\Attribute\Iconize;
use Base\Entity\User\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

class UserSettingsController extends AbstractController
{
    private $baseService;
    private UserRepository $userRepository;

    public function __construct(BaseService $baseService, UserRepository $userRepository)
    {
        $this->baseService = $baseService;
        $this->userRepository = $userRepository;
    }

    #[Route("/members/qr/totp", name: "qr_code_totp")]
    public function displayTotpQrCode(TokenStorageInterface $tokenStorage, TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        $user = $tokenStorage->getToken()->getUser();
        if (!($user instanceof TotpTwoFactorInterface)) {
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

    #[Route("/settings", name: "user_settings")]
    #[Iconize("fa-solid fa-fw fa-user-cog")]
    public function Settings()
    {
        $user = $this->getUser();
        return $this->render('client/user/settings.html.twig', ['user' => $user]);
    }

    #[Route("/settings/2fa", name: "user_settings_2fa")]
    public function TwoFactorAuthentification(Request $request)
    {
        $newUser = new User();
        $form = $this->createForm(Login2FAType::class, $newUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedToken = $request->request->get('login2_fa_form')["_csrf_token"] ?? null;
            if (!$this->isCsrfTokenValid('2fa', $submittedToken)) {
                $notification = new Notification("Invalid CSRF token detected. We cannot proceed with the 2FA authentification");
                $notification->send("danger");
            } else {
                $notification = new Notification("The 2FA authentification is not yet enabled. Try this later");
                $notification->send("danger");
                // $newUser->setTotpSecret($form->get('totpSecret')->getData());
                // $entityManager = $this->getDoctrine()->getManager();
                // $entityManager->persist($newUser);
                // $entityManager->flush();

                // if($user && $user->getIsVerified()) {

                //     $newUser->verify($user->getIsVerified());
                //     $this->baseService->addFlashSuccess("You've got successfully registered ! You account is already verified.");

                // } else {

                //     // generate a signed url and email it to the user
                //     $this->emailVerifier->sendEmailConfirmation('security_verifyEmailWithToken', $newUser,
                //         (new TemplatedEmail())
                //             ->from(new Address('support@example.com', 'Support'))
                //             ->to($newUser->getEmail())
                //             ->subject('Please Confirm your Email')
                //             ->htmlTemplate('email/user/registration_email.html.twig')
                //     );

                //     $this->baseService->addFlashSuccess("You've got successfully registered ! Please confirm your account by checking your email.");
                // }
            }
        }

        return $this->render('client/user/settings_2fa.html.twig', [
            'form' => $form->createView(),
            'user' => $this->getUser()
        ]);
    }

    #[Route("/settings/2fa/qr-code", name: "user_settings_2fa_qrcode")]
    public function TwoFactorAuthentification_QrCode(TotpAuthenticatorInterface $totpAuthenticator)
    {
        // Was written against the endroid v3 generator API and a
        // $this->qrCodeGenerator property that never existed: the route
        // fataled whenever hit. Route through the same builder as
        // /members/qr/totp instead.
        $user = $this->getUser();
        if (!($user instanceof TotpTwoFactorInterface)) {
            throw new NotFoundHttpException('Cannot display QR code');
        }

        if (empty($user->getTotpSecret())) {
            $user->setTotpSecret($totpAuthenticator->generateSecret());
        }

        return $this->displayQrCode($totpAuthenticator->getQRContent($user));
    }
}
