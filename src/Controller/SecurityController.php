<?php

namespace Base\Controller;

use App\Entity\User;

use Base\Entity\User\Notification;
use Base\Service\BaseService;
use Base\Security\LoginFormAuthenticator;

use App\Form\Type\Security\RegistrationType;
use App\Form\Type\Security\LoginType;
use App\Form\Type\Security\ChangePasswordType;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;
use Base\Entity\User\Token;

class SecurityController extends AbstractController
{
    protected $baseService;

    public function __construct(
        BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    /**
     * @Route("/clean/security", name="base_clean_security")
     */
    public function Clean()
    {
        throw new Exception("Clean UserLog not implemented yet");
    }

    /**
     * @Route("/login", name="base_login")
     */
    public function Login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Check if user connected
        if ( ($user = $this->getUser()) && $user->isLegit() ) {

            if ($this->isGranted('IS_AUTHENTICATED_FULLY'))
                return $this->redirectToRoute(($this->baseService->isMaintenance()) ? "dashboard": "base_profile");

            $notification = new Notification("Login", "notifications.login.partial");
            $notification->send("info");
        }

        // In case of maintenance, still allow users to login
        if($this->baseService->isMaintenance()) {

            $error = $authenticationUtils->getLastAuthenticationError();
            $lastUsername = $authenticationUtils->getLastUsername();

            return $this->render('@EasyAdmin/page/login.html.twig', [
                'error' => $error,
                'last_username' => $lastUsername,
                'translation_domain' => 'admin',
                'page_title' => 'Maintenance Login',
                'csrf_token_intention' => 'authenticate',
                'target_path' => $this->generateUrl('dashboard'),
                'username_label' => 'Your username',
                'password_label' => 'Your password',
                'sign_in_label' => 'Log in'
            ]);
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        if($error) {
            $notification = new Notification($error->getMessage());
            $notification->send("warning");
        }

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Generate form
        $user = new User();
        $form = $this->createForm(LoginType::class, $user, ["username" => $lastUsername]);
        $form->handleRequest($request);

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
            return $this->redirectToRoute('base_logout_request');

        // Check if the session is found.. meaning, the user just logged out
        if($user = $this->baseService->removeSession("_user")) {

            $message = "";
            if (method_exists(User::class, "getUsername")) {
                $username = $user->getUsername();
                $message = "Bye bye $username !";
            }

            $notification = new Notification("Bye !", "notifications.logout.success", [$message]);
            $notification->send("success");

            // Redirect to previous page
            $targetPath = $this->baseService->getRequest()->headers->get('referer') ?? null;
            if ($targetPath) return new RedirectResponse($targetPath);

        }

        return $this->redirectToRoute('base_homepage');
    }

    /**
     * @Route("/logout-request", name="base_logout_request")
     */
    public function LogoutRequest()
    {
        throw new Exception("This page should not be displayed.. Firewall should take over during logout process. Please check your configuration..");
    }

    /**
     * @Route("/register", name="base_register")
     */
    public function Register(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder,
        GuardAuthenticatorHandler $guardHandler,
        LoginFormAuthenticator $authenticator
    ): Response {
        // If already connected..
        if (($user = $this->getUser()) && $user->isLegit()) {
            return $this->redirectToRoute('base_profile');
        }

        // Prepare registration form
        $newUser = new User();
        $form = $this->createForm(
            RegistrationType::class,
            $newUser,
            ['validation_groups' => ['new']]
        );

        // An account might require to be verified by an admin
        $validationRequired = $this->baseService->getParameterBag("base_security.user.validation") ?? false;
        $newUser->approve(!$validationRequired);

        $form->handleRequest($request);

        // Registration form registered
        if ($form->isSubmitted() && $form->isValid()) {

            $submittedToken = $request->request->get('registration_form')["_csrf_token"] ?? null;
            if (!$this->isCsrfTokenValid('registration', $submittedToken)) {

                $notification = new Notification("Invalid token", "notification.register.csrfToken");
                $notification->send("danger");

            } else {

                $newUser->setPlainPassword($form->get('plainPassword')->getData());
                if ($user && $user->isVerified()) {

                    $newUser->verify($user->isVerified());
                    $notification = new Notification("Congratulations !", "notifications.register.verifyEmail.success");
                    $notification->send("success");

                } else {

                    $token = new Token('verify-email', 3600);
                    $user->addToken($token);

                    $notification = new Notification("Bravo !", "notifications.register.verifyEmail.check");
                    $notification->setHtmlTemplate('@Base/email/register.html.twig', [
                        'signedUrl' => "/register/verify-email/".$token
                    ]);
                    $notification->setUser($newUser);
                    $notification->send("success")->send("low");
                }

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($newUser);
                $entityManager->flush();
            }

            return $guardHandler->authenticateUserAndHandleSuccess(
                $newUser,
                $request,
                $authenticator,
                $firewall = 'main'
            );
        }

        // Retrieve form if no social account connected
        if (!$user) $user = $newUser;

        return $this->render('@Base/security/register.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    /**
     * @Route("/register/verify-email", name="base_register_email")
     */
    public function VerifyEmailRequest(Request $request, NotifierInterface $notifier)
    {
        // Check if accound is already verified..
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute("base_login");

        if ($user->isVerified()) {

            $notification = new Notification("Warning", "notifications.register.verifyEmail.success");
            $notification->send("success");

            return $this->redirectToRoute('base_settings');
        }

        $verifyEmailToken = $user->getValidToken("verify-email");
        if ($verifyEmailToken) {

            $remainingTime = ceil($verifyEmailToken->getRemainingTime()/60);
            $notification = new Notification("Please wait", "notifications.register.verifyEmail.resend", [$remainingTime]);
            $notification->send("danger");

           return $this->redirectToRoute('base_settings');
        }

        $verifyEmailToken = new Token("verify-email", 3600);
        $user->addToken($verifyEmailToken);

        $notification = new Notification('Confirm your account', 'notifications.register.verifyEmail.check');
        $notification->setUser($user);
        $notification->setHtmlTemplate("@Base/email/register.html.twig", [
            "signedUrl" => $this->baseService->getRouteWithUrl("base_verify_email", ["token" => $verifyEmailToken->get()]),
            "expiresAtMessageKey" => ceil($verifyEmailToken->getRemainingTime()/60)
        ]);
        $notification->send("success")->send("urgent");

        $this->getDoctrine()->getManager()->flush();
        return $this->redirectToRoute('base_settings');
    }

    /**
     * @Route("/register/verify-email/{token}", name="base_verify_email")
     */
    public function VerifyEmailResponse(Request $request, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        $verifyEmailToken = $user->getValidToken("verify-email");
        $user->removeToken($verifyEmailToken);

        if ($user->isVerified()) {

            $notification = new Notification("Bravo !", 'Your email address has already been verified !');
            $notification->setUser($user);
            $notification->send('warning');

        } else {

            if (!$verifyEmailToken) {

                $notification = new Notification("Invalid token", "Your email address could not been verified");
                $notification->send("danger");

            } else {

                if ($user->isApproved()) { // If the account needs further validation..

                    $notification = new Notification("Congratulations!", "Your email address has been verified !");
                    $notification->setUser($user);
                    $notification->send('success');
                    $user->setIsVerified(true);

                } else {

                    $notification = new Notification("New user account to validate");
                    $notification->setUser($user);
                    $notification->setHtmlTemplate("@Base/email/user/account_validation.html.twig");

                    $notification->send("success")->sendAdmins("low");
                }
            }
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute('base_homepage');
    }

    /**
     * Display & process form to request a password reset.
     *
     * @Route("/reset-password", name="base_password")
     */
    public function ResetPasswordRequest(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $submittedToken = $request->request->get('reset_password_request_form')["_csrf_token"] ?? null;

            if (!$this->isCsrfTokenValid('forgot-password', $submittedToken)) {

                $notification = new Notification("Invalid CSRF token detected. We cannot proceed with your request");
                $notification->send("danger");

            } else {

                return $this->processSendingPasswordResetEmail(
                    $form->get('email')->getData(),
                    $mailer
                );
            }
        }

        return $this->render('@Base/security/settings_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     *
     * @Route("/reset-password/{token}", name="base_password_token")
     */
    public function ResetPasswordResponse(Request $request, UserPasswordEncoderInterface $passwordEncoder, string $token = null): Response
    {
        if ($token) {
            // We store the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('base_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $notification = new Notification('reset_password_error', sprintf(
                'There was a problem validating your reset request - %s',
                $e->getReason()
            ));
            $notification->send("danger");
            return $this->redirectToRoute('base_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($token);

            // Encode the plain password, and set it.
            $encodedPassword = $passwordEncoder->encodePassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('base_login');
        }

        return $this->render('@Base/security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }
}
