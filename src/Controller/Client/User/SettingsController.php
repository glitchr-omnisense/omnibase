<?php

namespace Base\Controller\Client\User;
use Base\Service\BaseService;

use App\Entity\User;
use App\Repository\UserRepository;

use App\Form\User\Login2FAType;
use App\Form\User\ProfileEditType;
use App\Form\User\ProfileSearchType;
use Base\Entity\User\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use Endroid\QrCode\ErrorCorrectionLevel;
use Scheb\TwoFactorBundle\Security\TwoFactor\QrCode\QrCodeGenerator;

class SettingsController extends AbstractController
{
    private $baseService;
    public function __construct(BaseService $baseService, UserRepository $userRepository, QrCodeGenerator $qrCodeGenerator)
    {
        $this->baseService     = $baseService;
        $this->userRepository  = $userRepository;
        $this->qrCodeGenerator = $qrCodeGenerator;

    }

    /**
     * @Route("/settings", name="base_user_settings")
     */
    public function Settings()
    {
        $user = $this->getUser();
        return $this->render('@Base/client/user/settings.html.twig', ['user' => $user]);
    }

    /**
     * @Route("/settings/2fa", name="base_user_settings_2fa")
     */
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

        return $this->render('@Base/client/user/settings_2fa.html.twig', [
           'form' => $form->createView(),
           'user' => $this->getUser()
        ]);

    }

     /**
     * @Route("/settings/2fa/qr-code", name="base_user_settings_2fa_qrcode")
     */
    public function TwoFactorAuthentification_QrCode()
    {
        $user = $this->getUser();

        if(empty($user->getTotpSecret())) {
            $totpAuthenticator = $this->baseService->getContainer("scheb_two_factor.security.totp_authenticator");
            $user->setTotpSecret( $totpAuthenticator->generateSecret() );
        }

        $qrCode = $this->qrCodeGenerator->getTotpQrCode($user);
        $qrCode->setSize(250);
        $qrCode->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0));
        $qrCode->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0));
        $qrCode->setLogoPath("/assets/ico/favicon_qr.png");
        $qrCode->setLogoSize(128);
        $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::QUARTILE());


        return new Response($qrCode->writeString(), 250, ['Content-Type' => 'image/png']);
    }
}
