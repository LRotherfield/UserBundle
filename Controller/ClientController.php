<?php

namespace Rothers\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Security\Core\SecurityContext;
use Rothers\UserBundle\Form\UserType;
use Rothers\UserBundle\Form\ForgottenPasswordType;
use Rothers\UserBundle\Form\ResetPasswordType;
use Rothers\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use JMS\SecurityExtraBundle\Annotation\Secure;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @Route("/account")
 */
class ClientController extends Controller
{

    /**
     * @Route("/register", name="user_register")
     * @Template
     */
    public function registerAction()
    {
        $user = new User();
        $form = $this->createForm(new UserType('front', 'new'), $user);
        if ($this->getRequest()->getMethod() == 'POST') {
            $form->bindRequest($this->getRequest());
            if ($form->isValid()) {
                $role = $this->getDoctrine()->getRepository($this->container->getParameter('userextensionbundle.name') . ':Role')->findOneByRole('ROLE_USER');
                $user->setActive(0);
                $user->addRole($role);
                $password = $this->encodePassword($user);
                $user->setPassword($password);
                $em = $this->getDoctrine()->getEntityManager();
                $em->persist($user);
                $em->flush();
                $message = \Swift_Message::newInstance()
                    ->setSubject('Account confirmation')
                    ->setTo($user->getEmail())
                    ->setFrom($this->container->getParameter('userbundle.from.email'))
                    ->setBody($this->renderView($this->container->getParameter('userextensionbundle.name') . ':Email:register.html.twig', array('user' => $user)), 'text/html');
                $this->get('mailer')->send($message);
                $this->get('session')->setFlash('notice', 'Thank you, you must now confirm your account');
                $message = $this->getMessage("Thank you for registering for an account, and email has been sent to your account with a link.  Please click the link to confirm your account.");
                return $message;
            }
        }
        return array('form' => $form->createView());
    }

    private function encodePassword($user, $password = false)
    {
        $factory = $this->get('security.encoder_factory');
        $encoder = $factory->getEncoder($user);
        return $encoder->encodePassword($password ? $password : $user->getPassword(), $user->getSalt());
    }

    /**
     * @Route("/register/thank-you", name="user_register_thankyou")
     * @Template
     */
    public function getMessage($message)
    {
        return $this->render($this->container->getParameter('userextensionbundle.name') . ':Client:thankyou.html.twig', array(
                'message' => $message
            ));
    }

    /**
     * @Route("/confirm/{id}/{token}", name="user_register_confirm")
     * @Template
     */
    public function confirmAction($id, $token)
    {
        $user = $this->getDoctrine()
            ->getRepository($this->container->getParameter('userextensionbundle.name') . ':User')
            ->findOneBy(array('id' => $id, 'token' => $token));
        if (!$user)
            throw $this->createNotFoundException("Sorry this request is not valid, if you followed an email link please contact the site administrator");
        $user->setActive(1);
        $em = $this->getDoctrine()->getEntityManager();
        $em->persist($user);
        $em->flush();
        $token = new UsernamePasswordToken($user, null, 'secured_area', $user->getRoles());
        $this->get('security.context')->setToken($token);
        $this->get('session')->setFlash('notice', 'Your account has been confirmed and you are now logged in');
        return new RedirectResponse($this->generateUrl('user_account_show'));
    }

    /**
     * @Route("/", name="user_account_show")
     * @Secure(roles="ROLE_USER")
     * @Template
     */
    public function showAction()
    {
        $user = $this->get('security.context')->getToken()->getUser();
        if (!$user)
            throw $this->createNotFoundException('User account not found');
        return array('user' => $user);
    }

    /**
     * @Route("/forgotten-password", name="user_forgotten_password")
     * @Template
     */
    public function forgottenPasswordAction()
    {
        $form = $this->createForm(new ForgottenPasswordType());
        $request = $this->getRequest();
        $form->bindRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();
            $user = $this->getDoctrine()->getRepository($this->container->getParameter('userextensionbundle.name') . ':User')->findOneBy(array('email' => $data['email']));
            if (!$user)
                throw $this->createNotFoundException('User account not found');
            $message = \Swift_Message::newInstance()
                ->setSubject('Forgotten Password')
                ->setTo($user->getEmail())
                ->setFrom($this->container->getParameter('userbundle.from.email'))
                ->setBody($this->renderView('UserBundle:Email:forgotten_password.html.twig', array('user' => $user)), 'text/html');
            $this->get('mailer')->send($message);
            $this->get('session')->setFlash('notice', 'Thank you, you must now confirm your account');
            $message = $this->getMessage("An email has been sent to your account to start the password reset procedure.  Please click the link sent to continue.");
            return $message;
        }
        return array('form' => $form->createView());
    }

    /**
     * @Route("/reset-password/{id}/{token}", name="user_reset_password")
     * @Template
     */
    public function resetPasswordAction($id, $token)
    {
        $user = $this->getDoctrine()->getRepository($this->container->getParameter('userextensionbundle.name') . ':User')->findOneBy(array('id' => $id, 'token' => $token));
        if (!$user)
            throw $this->createNotFoundException('User account not found');
        $form = $this->createForm(new ResetPasswordType());
        $request = $this->getRequest();
        $form->bindRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();
            $user->setPassword($this->encodePassword($user, $data['password']));
            $user->setToken(sha1($this->generateToken(32)));
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($user);
            $em->flush();
            $this->get('session')->setFlash('notice', 'Your password has been updated, you can now login with your new credentials.');
            return new RedirectResponse($this->generateUrl('_login'));
        }
        return array(
            'form' => $form->createView(),
            'id' => $id,
            'token' => $token
        );
    }

    public function generateToken($length=12)
    {
        $string = 'qwertyuiopasdfghjklzxcvbnm1234567890';
        $random = '';
        for ($i = 0; $i < $length; $i++)
            $random .= $string[rand(0, strlen($string) - 1)];
        return $random;
    }

}
