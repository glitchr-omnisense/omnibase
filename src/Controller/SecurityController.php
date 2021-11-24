<?php

namespace Base\Controller;

use App\Entity\User;

use Base\Entity\User\Notification;
use Base\Service\BaseService;
use Base\Security\LoginFormAuthenticator;

use App\Form\Type\Security\RegistrationType;
use App\Form\Type\Security\LoginType;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

use Symfony\Component\Notifier\NotifierInterface;

use Base\Entity\User\Token;
use Base\Form\Type\Security\ResetPasswordType;
use App\Repository\UserRepository;
use Base\Form\Type\Security\ResetPasswordConfirmType;
use Base\Repository\User\TokenRepository;

class SecurityController extends AbstractController
{
    protected $baseService;

    public function __construct(BaseService $baseService, UserRepository $userRepository, TokenRepository $tokenRepository)
    {
        $this->baseService = $baseService;
        $this->userRepository = $userRepository;
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * @Route("/login", name="base_login")
     */
    public function Login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // In case of maintenance, still allow users to login
        if($this->baseService->isMaintenance()) {

            if ( ($user = $this->getUser()) && $user->isPersistent() )
            return $this->redirectToRoute("base_dashboard");

            $lastUsername = $authenticationUtils->getLastUsername();

            $logo = $this->baseService->getSettings("base.settings.logo");
            return $this->render('@EasyAdmin/page/login.html.twig', [
                'last_username' => $lastUsername,
                'translation_domain' => 'admin',
                'csrf_token_intention' => 'authenticate',
                'target_path' => $this->baseService->getUrl('base_dashboard'),
                'username_label' => 'Your username',
                'password_label' => 'Your password',
                'sign_in_label' => 'Log in',
                'page_title' => '<img src="'.$logo.'" alt="Dashboard">'
            ]);
        }

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Redirect to the right page when access denied
        if ( ($user = $this->getUser()) && $user->isPersistent() ) {

            if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {

                // Check if target path provided via $_POST..
                $targetPath = 
                    $this->baseService->getRoute($request->request->get("_target_path")) ??
                    $this->baseService->getRoute($request->getSession()->get('_security.main.target_path')) ?? 
                    $this->baseService->getRoute($request->getSession()->get('_security.account.target_path'));

                $request->getSession()->set('_security.main.target_path', null);
                $request->getSession()->set('_security.account.target_path', null);
                
                if ($targetPath &&
                    $targetPath != LoginFormAuthenticator::LOGOUT_ROUTE &&
                    $targetPath != LoginFormAuthenticator::LOGIN_ROUTE )
                    return $this->redirectToRoute($targetPath);

                return $this->redirectToRoute($request->isMethod('POST') ? "base_settings" : $this->baseService->getRoute("/"));
            }

            $notification = new Notification("notifications.login.partial");
            $notification->send("info");
        }

        // Generate form
        $user = new User();
        $form = $this->createForm(LoginType::class, $user, ["username" => $lastUsername]);
        $form->handleRequest($request);
        
        // Remove expired tokens
        $user->removeExpiredTokens();

        return $this->render('@Base/security/login.html.twig', [
            "username" => $lastUsername,
            "form" => $form->createView()
        ]);
    }

    /**
     * @Route("/logout", name="base_logout")
     */
    public function Logout() {

        // If user is found.. go to the logout request page
        if($this->getUser())
            return $this->redirectToRoute('base_logoutRequest');

        // Check if the session is found.. meaning, the user just logged out
        if($user = $this->baseService->removeSession("_user")) {

            $message = "";
            if (method_exists(User::class, "getUsername")) {
                $username = $user->getUsername();
                $message = "Bye bye $username !";
            }

            $notification = new Notification("notifications.logout.success", [$message]);
            $notification->send("success");

            // Remove expired tokens
            $user->removeExpiredTokens();
            
            // Redirect to previous page
            $targetPath = $this->baseService->getRequest()->headers->get('referer') ?? null;
            if ($targetPath) return $this->redirect($targetPath);

        }

        return $this->redirectToRoute('base_homepage');
    }

    /**
     * @Route("/logout-request", name="base_logoutRequest")
     */
    public function LogoutRequest()
    {
        throw new Exception("This page should not be displayed.. Firewall should take over during logout process. Please check your configuration..");
    }

    /**
     * @Route("/register", name="base_register")
     */
    public function Register(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator): Response {

        // If already connected..
        if (($user = $this->getUser()) && $user->isPersistent())
            return $this->redirectToRoute('base_profile');

        // Prepare registration form
        $newUser = new User();
        $form = $this->createForm(RegistrationType::class, $newUser, ['validation_groups' => ['new']]);

        // An account might require to be verified by an admin
        $adminApprovalRequired = $this->baseService->getParameterBag("base_security.user.adminApproval") ?? false;
        $newUser->approve(!$adminApprovalRequired);

        $form->handleRequest($request);

        // Registration form registered
        if ($form->isSubmitted() && $form->isValid()) {

        
            $newUser->setPlainPassword($form->get('plainPassword')->getData());
            if ($user && $user->isVerified()) // Social account connection
                $newUser->verify($user->isVerified());

            $this->getDoctrine()->getManager()->persist($newUser);
            $this->getDoctrine()->getManager()->flush();

            return $userAuthenticator->authenticateUser($newUser, $authenticator, $request);
        }

        // Retrieve form if no social account connected
        if (!$user) $user = $newUser;

        return $this->render('@Base/security/register.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    /**
     * @Route("/verify-email", name="base_verifyEmail")
     * @IsGranted("ROLE_USER")
     */
    public function VerifyEmailRequest(Request $request, NotifierInterface $notifier)
    {
        // Check if accound is already verified..
        $user = $this->getUser();
        if ($user->isVerified()) {

            $notification = new Notification("notifications.verifyEmail.already");
            $notification->send("success");

        } else if ( ($verifyEmailToken = $user->getValidToken("verify-email")) ) {

            $notification = new Notification("notifications.verifyEmail.resend", [$verifyEmailToken->getRemainingTimeStr()]);
            $notification->send("danger");

        } else {

            $verifyEmailToken = new Token("verify-email", 24*3600);
            $verifyEmailToken->setUser($user);

            $notification = new Notification('notifications.verifyEmail.check');
            $notification->setUser($user);
            $notification->setHtmlTemplate("@Base/security/email/verify_email.html.twig", ["token" => $verifyEmailToken]);
            $notification->send("success")->send("urgent");
        }

        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('base_profile');
    }

    /**
     * @Route("/verify-email/{token}", name="base_verifyEmail_token")
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function VerifyEmailResponse(Request $request): Response
    {
        $user = $this->getUser();
        $user->removeExpiredTokens("verify-email");
            
        if ($user->isVerified()) {

            $notification = new Notification('notifications.verifyEmail.already');
            $notification->setUser($user);
            $notification->send('warning');

        } else if (!($verifyEmailToken = $user->getValidToken("verify-email"))) {

            $notification = new Notification("notifications.verifyEmail.invalidToken");
            $notification->send("danger");

        } else {

            $user->setIsVerified(true);
            $verifyEmailToken->revoke();

            $notification = new Notification("notifications.verifyEmail.success");
            $notification->setUser($user);
            $notification->send('success');

            if (!$user->isApproved()) // If the account needs further validation by admin..
                $this->AdminApprovalRequest($request);
        }

        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('base_profile');
    }

    /**
     * @Route("/admin-approval", name="base_adminApproval")
     */
    public function AdminApprovalRequest(Request $request)
    {
        $user = $this->getUser();
        $user->removeExpiredTokens("admin-approval");

        if(!$user->isVerified()) {

            $notification = new Notification("notifications.adminApproval.verifyFirst");  
            $notification->send("warning");

        } else if (!$user->isApproved()) {

            if ( ($adminApprovalToken = $user->getValidToken("admin-approval")) ) { 

                $notification = new Notification("notifications.adminApproval.alreadySent");  
                $notification->send("warning");

            } else {

                $adminApprovalToken = new Token("admin-approval");
                $adminApprovalToken->setUser($user);
            
                $notification = new Notification("notifications.adminApproval.required");
                $notification->setUser($user);
                $notification->setHtmlTemplate("@Base/security/email/admin_approval.html.twig",["token" => $adminApprovalToken]);
                $notification->sendAdmins("low")->send("success");
            }
        }

        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('base_profile');
    }

    /**
     * @Route("/account-goodbye", name="base_accountGoodbye")
     */
    public function DisableAccountRequest(Request $request)
    {
        $user = $this->getUser();

        if($user->isDisabled()) {

            $notification = new Notification("notifications.accountGoodbye.already");  
            $notification->send("warning");

            return $this->redirectToRoute('base_homepage');

        } else {

            $user->disable();

            $this->baseService->Logout();

            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('base_homepage');
        }
    }

    /**
     * @Route("/welcome-back/{token}", name="base_accountWelcomeBack_token")
     */
    public function EnableAccountRequest(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, string $token = null): Response
    {
        $user = null;

        $welcomeBackToken = $this->tokenRepository->findOneByValue($token);
        if($welcomeBackToken) $user = $welcomeBackToken->getUser();

        if($user === null || !$welcomeBackToken || !$welcomeBackToken->isValid()) {

            if ($welcomeBackToken)
                $welcomeBackToken->revoke();
            
            $notification = new Notification("notifications.accountWelcomeBack.invalidToken");  
            $notification->send("danger");
            
            return $this->redirectToRoute('base_homepage');
        
        } else if(!$user->isDisabled()) {

            $welcomeBackToken->revoke();
            
            $notification = new Notification("notifications.accountWelcomeBack.already");  
            $notification->send("warning");

            return $this->redirectToRoute('base_homepage');

        
        } else if ($user->getValidToken("welcome-back")){

            $user->enable();

            $authenticateUser = $userAuthenticator->authenticateUser($user, $authenticator, $request);
            
            $this->getDoctrine()->getManager()->flush();
            return $authenticateUser;
        }
    }

    /**
     * Display & process form to request a password reset.
     *
     * @Route("/reset-password", name="base_resetPassword")
     */
    public function ResetPasswordRequest(Request $request): Response
    {
        if (($user = $this->getUser()) && $user->isPersistent())
            return $this->redirectToRoute('base_profile');
            
        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $notification = new Notification("notifications.resetPassword.confirmation");

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

            $this->getDoctrine()->getManager()->flush();
            $notification->send("success");
        }

        return $this->render('@Base/security/reset_password_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     *
     * @Route("/reset-password/{token}", name="base_resetPassword_token")
     */
    public function ResetPasswordResponse(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, string $token = null): Response
    {
        if (($user = $this->getUser()) && $user->isPersistent())
            return $this->redirectToRoute('base_profile');
            
        $resetPasswordToken = $this->tokenRepository->findOneByValue($token);
        if (!$resetPasswordToken) {

            $notification = new Notification("notifications.resetPassword.invalidToken");
            $notification->send("danger");

            return $this->redirectToRoute('base_homepage');

        } else {

            $user = $resetPasswordToken->getUser();

            // The token is valid; allow the user to change their password.
            $form = $this->createForm(ResetPasswordConfirmType::class);
            $form->handleRequest($request);
        
            if ($form->isSubmitted() && $form->isValid()) {

                $resetPasswordToken->revoke();
                $user->setPlainPassword($form->get('plainPassword')->getData());

                $notification = new Notification("notifications.resetPassword.success");
               
                $this->getDoctrine()->getManager()->flush();
                $authenticateUser = $userAuthenticator->authenticateUser($user, $authenticator, $request);
                $notification->send("success");

                return $authenticateUser;
            }

            return $this->render('@Base/security/reset_password.html.twig', ['form' => $form->createView()]);
        }
    }
}
