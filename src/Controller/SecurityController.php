<?php

namespace Base\Controller;

use App\Entity\User;

use Base\Entity\User\Notification;
use Base\Service\BaseService;
use Base\Security\LoginFormAuthenticator;

use App\Form\Type\SecurityRegistrationType;
use App\Form\Type\SecurityLoginType;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

use Symfony\Component\Notifier\NotifierInterface;

use Base\Entity\User\Token;
use Base\Form\Type\SecurityResetPasswordType;
use App\Repository\UserRepository;
use Base\Annotations\Annotation\Iconize;
use Base\Form\FormProxy;
use Base\Form\FormProcessorInterface;
use Base\Service\ReferrerInterface;
use Base\Form\Type\SecurityResetPasswordConfirmType;
use Base\Repository\User\TokenRepository;

use Base\Security\RescueFormAuthenticator;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\MaternityServiceInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use DateTime;
use Doctrine\ORM\EntityManager;

class SecurityController extends AbstractController
{
    protected $baseService;

    public function __construct(
        EntityManager $entityManager, TokenRepository $tokenRepository, UserRepository $userRepository,
        BaseService $baseService, FormProxy $formProxy, TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator, ParameterBagInterface $parameterBag)
    {
        $this->baseService     = $baseService;
        $this->translator      = $translator;
        $this->tokenStorage    = $tokenStorage;
        $this->formProxy       = $formProxy;
        $this->parameterBag    = $parameterBag;
        
        $this->entityManager   = $entityManager;
        $this->userRepository  = $userRepository;
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * @Route("/login", name="security_login")
     * @Iconize("fas fa-fw fa-sign-in-alt")
     */
    public function Login(Request $request, ReferrerInterface $referrer, AuthenticationUtils $authenticationUtils): Response
    {
        // In case of maintenance, still allow users to login
        if($this->isGranted("EXCEPTION_ACCESS"))
            return $this->redirectToRoute(RescueFormAuthenticator::LOGIN_ROUTE);

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Redirect to the right page when access denied
        if ( ($user = $this->getUser()) ) {

            // Remove expired tokens
            $user->removeExpiredTokens();
            
            if($this->isGranted('IS_AUTHENTICATED_FULLY'))
                return $this->redirect($referrer->getUrl() ?? $this->baseService->getAsset("/"));

            $notification = new Notification("login.partial");
            $notification->send("info");
        }

        // Generate form
        $formProcessor = $this->formProxy
            ->createProcessor("form:login", SecurityLoginType::class, ["identifier" => $lastUsername])
            ->handleRequest($request);

        return $this->render('@Base/security/login.html.twig', [
            "identifier" => $lastUsername,
            "form" => $formProcessor->getForm()->createView()
        ]);
    }

    /**
     * @Route("/logout", name="security_logout")
     * @Iconize("fas fa-fw fa-sign-out-alt")
     */
    public function Logout(ReferrerInterface $referrer, Request $request)
    {
        // If user is found.. go to the logout request page
        if($this->getUser()) {

            $response = $this->redirectToRoute(LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE);
            $response->headers->clearCookie('REMEMBERME', "/");
            $response->headers->clearCookie('REMEMBERME', "/", ".".format_url(get_url(),FORMAT_URL_NOMACHINE|FORMAT_URL_NOSUBDOMAIN));

            return $response;
        }

        // Check if the session is found.. meaning, the user just logged out
        if($user = $this->baseService->removeSession("_user")) {

            $message = "Bye bye $user !";

            if( $user->isKicked()   ) {

                $notification = new Notification("kickout", [$user]);
                $notification->setUser($user);
                $notification->send("warning");
                $user->kick(0);

            } else {

                $notification = new Notification("logout.success", [$message]);
                $notification->send("info");
            }

            // Remove expired tokens
            $user->removeExpiredTokens();
        }

        // Redirect to previous page
        return $this->redirect($referrer->getUrl() ?? $this->baseService->getAsset("/"));
    }

    /**
     * @Route("/logout-request", name="security_logoutRequest")
     */
    public function LogoutRequest()
    {
        throw new Exception("This page should not be displayed.. Firewall should take over during logout process. Please check your configuration..");
    }

    /**
     * @Route("/register", name="security_register")
     */
    public function Register(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, ParameterBagInterface $parameterBag): Response {

        // If already connected..
        if (($user = $this->getUser()) && $user->isPersistent())
            return $this->redirectToRoute('user_profile');

        // Prepare registration form
        $formProcessor = $this->formProxy->createProcessor("form:login", SecurityRegistrationType::class, [
                'validation_groups' => ['new'],
                'validation_entity' => User::class
            ])
            ->onSubmit(function(FormProcessorInterface $formProcessor, Request $request) use ($userAuthenticator, $authenticator){

                $newUser = $formProcessor->hydrate((new User()));

                // An account might require to be verified by an admin
                $adminApprovalRequired = $this->parameterBag->get("security.user.adminApproval") ?? false;
                $newUser->approve(!$adminApprovalRequired);
                
                $newUser->setPlainPassword($formProcessor->getData("plainPassword"));
                
                // Social account connection
                if (($user = $this->getUser()) && $user->isVerified())
                    $newUser->verify($user->isVerified());

                $this->entityManager->persist($newUser);
                $this->entityManager->flush();

                return $userAuthenticator->authenticateUser($newUser, $authenticator, $request);
            })

            ->onDefault(function(FormProcessorInterface $formProcessor) {

                return $this->render('@Base/security/register.html.twig', [
                    'form' => $formProcessor->getForm()->createView(),
                    'user' => $formProcessor->getData()
                ]);
            })

            ->handleRequest($request);

        return $formProcessor->getResponse();
    }

    /**
     * @Route("/verify-email", name="security_verifyEmail")
     * @IsGranted("ROLE_USER")
     */
    public function VerifyEmailRequest(Request $request, NotifierInterface $notifier)
    {
        // Check if accound is already verified..
        $user = $this->getUser();
        if ($user->isVerified()) {

            $notification = new Notification("verifyEmail.already");
            $notification->send("info");

        } else {

            $verifyEmailToken = $user->getToken("verify-email");
            if($verifyEmailToken && $verifyEmailToken->hasVeto()) {

                $notification = new Notification("verifyEmail.resend", [$verifyEmailToken->getRemainingTimeStr()]);
                $notification->send("danger");

            } else {

                $verifyEmailToken = new Token("verify-email", 24*3600, 3600);
                $verifyEmailToken->setUser($user);

                $notification = new Notification('verifyEmail.check');
                $notification->setUser($user);
                $notification->setHtmlTemplate("@Base/security/email/verify_email.html.twig", ["token" => $verifyEmailToken]);
                $notification->send("success")->send("urgent");
            }
        }

        $this->entityManager->flush();
        return $this->redirectToRoute('user_profile');
    }

    /**
     * @Route("/verify-email/{token}", name="security_verifyEmailWithToken")
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function VerifyEmailResponse(Request $request, string $token): Response
    {
        $user = $this->getUser();
        $user->removeExpiredTokens("verify-email");

        if ($user->isVerified()) {

            $notification = new Notification('verifyEmail.already');
            $notification->setUser($user);
            $notification->send('info');

        } else {

            $verifyEmailToken = $user->getValidToken("verify-email");
            if (!$verifyEmailToken || $verifyEmailToken->get() != $token) {

                $notification = new Notification("verifyEmail.invalidToken");
                $notification->setUser($user);
                $notification->send("danger");

            } else {

                $user->verify(true);
                $verifyEmailToken->revoke();

                $notification = new Notification("verifyEmail.success");
                $notification->setUser($user);
                $notification->send('success');

                if (!$user->isApproved()) // If the account needs further validation by admin..
                    $this->AdminApprovalRequest($request);
            }
        }

        $this->entityManager->flush();
        return $this->redirectToRoute('user_profile');
    }

    /**
     * @Route("/admin-approval", name="security_adminApproval")
     */
    public function AdminApprovalRequest(Request $request)
    {
        $user = $this->getUser();
        $user->removeExpiredTokens("admin-approval");

        if(!$user->isVerified()) {

            $notification = new Notification("adminApproval.verifyFirst");
            $notification->send("warning");

        } else if (!$user->isApproved()) {

            if ( ($adminApprovalToken = $user->getValidToken("admin-approval")) ) {

                $notification = new Notification("adminApproval.alreadySent");
                $notification->send("warning");

            } else {

                $adminApprovalToken = new Token("admin-approval");
                $adminApprovalToken->setUser($user);

                $notification = new Notification("adminApproval.required");
                $notification->setUser($user);
                $notification->setHtmlTemplate("@Base/security/email/admin_approval.html.twig",["token" => $adminApprovalToken]);
                $notification->sendAdmins("low")->send("success");
            }
        }

        $this->entityManager->flush();
        return $this->redirectToRoute('user_profile');
    }

    /**
     * @Route("/account-goodbye", name="security_accountGoodbye")
     */
    public function DisableAccountRequest(Request $request)
    {
        $user = $this->getUser();

        if($user->isDisabled()) {

            $notification = new Notification("accountGoodbye.already");
            $notification->send("warning");

            return $this->redirectToRoute($this->baseService->getRouteName("/"));

        } else {

            $user->disable();
            $user->logout();

            $this->entityManager->flush();
            return $this->redirectToRoute($this->baseService->getRouteName("/"));
        }
    }

    /**
     * @Route("/welcome-back/{token}", name="security_accountWelcomeBackWithToken")
     */
    public function EnableAccountRequest(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, string $token = null): Response
    {
        $welcomeBackToken = $this->tokenRepository->findOneByValueAndName($token, "welcome-back");
        $user = $welcomeBackToken ? $welcomeBackToken->getUser() : $this->getUser();

        if($user && !$user->isDisabled()) {

            $welcomeBackToken->revoke();

            $notification = new Notification("accountWelcomeBack.already");
            $notification->send("warning");

        } else if ($user && $user->getValidToken("welcome-back")){

            $user->enable();
            $authenticateUser = $userAuthenticator->authenticateUser($user, $authenticator, $request);

            $this->entityManager->flush();
            return $authenticateUser;

        } else {

            if ($welcomeBackToken) {

                $welcomeBackToken->revoke();
                $this->entityManager->flush();
            }

            $notification = new Notification("accountWelcomeBack.invalidToken");
            $notification->send("danger");
        }

        return $this->redirectToRoute($this->baseService->getRouteName("/"));
    }

    /**
     * Display & process form to request a password reset.
     *
     * @Route("/reset-password", name="security_resetPassword")
     */
    public function ResetPasswordRequest(Request $request): Response
    {
        if (($user = $this->getUser()) && $user->isPersistent())
            return $this->redirectToRoute('user_profile');

        $form = $this->createForm(SecurityResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $notification = new Notification("resetPassword.confirmation");

            $email = $username = $form->get('email')->getData();
            if( ($user = $this->userRepository->findOneByUsernameOrEmail($email, $username)) ) {

                $user->removeExpiredTokens("reset-password");
                if (!$user->getToken("reset-password")) {

                    $resetPasswordToken = new Token("reset-password", 3600);
                    $resetPasswordToken->setUser($user);

                    $notification->setHtmlTemplate("@Base/security/email/reset_password.html.twig", ["token" => $resetPasswordToken]);
                    $notification->setUser($user);
                    $notification->send("email");
                }
            }

            $this->entityManager->flush();
            $notification->send("success");
        }

        return $this->render('@Base/security/reset_password_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     *
     * @Route("/reset-password/{token}", name="security_resetPasswordWithToken")
     */
    public function ResetPasswordResponse(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, string $token = null): Response
    {
        if (($user = $this->getUser()) && $user->isPersistent())
            return $this->redirectToRoute('user_profile');

        $resetPasswordToken = $this->tokenRepository->findOneByValue($token);
        if (!$resetPasswordToken) {

            $notification = new Notification("resetPassword.invalidToken");
            $notification->send("danger");

            return $this->redirectToRoute($this->baseService->getRouteName("/"));

        } else {

            $user = $resetPasswordToken->getUser();

            // The token is valid; allow the user to change their password.
            $form = $this->createForm(SecurityResetPasswordConfirmType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $resetPasswordToken->revoke();
                $user->setPlainPassword($form->get('plainPassword')->getData());

                $notification = new Notification("resetPassword.success");

                $this->entityManager->flush();
                $authenticateUser = $userAuthenticator->authenticateUser($user, $authenticator, $request);
                $notification->send("success");

                return $authenticateUser;
            }

            return $this->render('@Base/security/reset_password.html.twig', ['form' => $form->createView()]);
        }
    }

    /**
     * Link to this controller to start the maintenance
     *
     * @Route("/m", name="security_maintenance")
     */
    public function Maintenance(MaintenanceProviderInterface $maintenanceProvider): Response
    {
        return $this->render('@Base/security/maintenance.html.twig', [
            'remainingTime' => $maintenanceProvider->getRemainingTime(),
            'percentage'    => $maintenanceProvider->getPercentage(),
            'downtime'      => $maintenanceProvider->getDowntime(),
            'uptime'        => $maintenanceProvider->getUptime()
        ]);
    }


    /**
     * Link to this controller to start the birth
     *
     * @Route({"fr": "/est/bientot/en/ligne", "en":"/is/coming/soon"}, name="security_birth")
     */
    public function Birth(MaternityServiceInterface $maternityService): Response
    {
        return $this->render('@Base/security/birthdate.html.twig', [
            'birthdate'  => $maternityService->getBirthdate(),
            'is_born'    => $maternityService->isBorn()
        ]);
    }
}
