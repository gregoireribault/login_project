<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ForgottenPasswordType;
use App\Form\ResetPasswordType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error) {
            $this->addFlash('danger', $error->getMessage());
        }
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        // No implementation needed as it's handled by firewall
    }

    /**
     * @Route("/forgotten-password", name="app_forgotten_password")
     */
    public function forgottenPassword(
        Request $request,
        \Swift_Mailer $mailer,
        TokenGeneratorInterface $tokenGenerator
    ): Response
    {
        $form = $this->createForm(ForgottenPasswordType::class);
        $form->handleRequest($request);

        if ($request->isMethod(Request::METHOD_POST) && $form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];

            $entityManager = $this->getDoctrine()->getManager();
            /** @var User $user */
            $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['email' => $email]);

            // Sent a reset password email only if the given email matches a user
            if ($user) {
                $token = $tokenGenerator->generateToken();
                $user->setResetPasswordToken($token);
                $entityManager->persist($user);
                $entityManager->flush($user);
                $url = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                $message = new \Swift_Message('Login Project - Password Reset');
                $message->setFrom(['gregoire_ribault@hotmail.com'=> 'Login Project'])
                    ->setTo($user->getEmail())
                    ->setBody(
                        $this->renderView('security/forgottenPasswordEmail.html.twig', [
                            'user' => $user,
                            'url' => $url
                        ]),
                        'text/html'
                    );
                $mailer->send($message);
            }

            // Whatever email was set, display this flash message to prevent the current user from knowing whether this email is saved in db or not
            $this->addFlash('success', sprintf('An email was sent to %s, please click on the link in it to reset your password', $email));
        }

        return $this->render('security/forgottenPassword.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/reset-password/{token}", name="app_reset_password")
     * @ParamConverter("user", options={"mapping": {"token": "resetPasswordToken"}})
     */
    public function resetPasswordAction(User $user, Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        // TODO token expiration

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($request->isMethod(Request::METHOD_POST) && $form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $password = $passwordEncoder->encodePassword($user, $data->getPlainPassword());
            $user->setPassword($password);
            $user->setResetPasswordToken(null);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Password is reset!');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/resetPassword.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
