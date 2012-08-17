<?php

namespace Rothers\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Security\Core\SecurityContext;
use Rothers\UserBundle\Form\UserType;
use Rothers\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @Route("/admin/user")
 */
class SecuredController extends Controller {

  /**
   * @Route("/new", name="new_user")
   * @Template("UserBundle:Secured:new.html.twig")
   */
  public function newAction() {
    $userclass = $this->container->getParameter('userextensionbundle.namespace');
    $user = new $userclass;
    $form = $this->createForm(new UserType('admin', 'new'), $user);
    if ($this->processForm($form, $user, true))
      return new RedirectResponse($this->generateUrl('show_users'));
    return array('form' => $form->createView());
  }

  /**
   * @Route("/edit/{id}", name="edit_user")
   * @Template("UserBundle:Secured:edit.html.twig")
   */
  public function editAction($id) {
    $user = $this->getDoctrine()->getRepository($this->container->getParameter('userextensionbundle.name').':User')->find($id);
    if (!$user)
      throw $this->createNotFoundException('Sorry user not found');
    $form = $this->createForm(new UserType('admin', 'edit'), $user);
    if ($this->processForm($form, $user))
      return new RedirectResponse($this->generateUrl('show_users'));
    return array(
        'form' => $form->createView(),
        'user' => $user
    );
  }

  /**
   * @Route("/delete/{id}", name="delete_user")
   */
  public function deleteAction($id) {
    $em = $this->getDoctrine()->getEntityManager();
    $user = $em->getRepository($this->container->getParameter('userextensionbundle.name').':User')->find($this->getRequest()->get('id'));
    if (!$user)
      throw $this->createNotFoundException('Sorry user not found');
    $form = $this->createDeleteForm($this->getRequest()->get('id'));
    $form->bindRequest($this->getRequest());
    if ($form->isValid()) {
      $em->remove($user);
      $em->flush();
      $this->get('session')->setFlash('notice', 'User deleted');
    }
    return new RedirectResponse($this->generateUrl('show_users'));
  }

  /**
   * @Route("/", name="show_users")
   * @Template("UserBundle:Secured:index.html.twig")
   */
  public function indexAction() {
    $query = $this->getDoctrine()->getRepository($this->container->getParameter('userextensionbundle.name').':User')->createQueryBuilder('u');
    $query = $query->getQuery();
    $users = $query->getResult();
    foreach ($users as $user)
      $user->setDeleteForm($this->createDeleteForm($user->getId())->createView());

    return array(
        'users' => $users,
    );
  }

  private function processForm($form, $user, $new = false) {
    if ($this->getRequest()->getMethod() == 'POST') {
      $form->bindRequest($this->getRequest());
      if ($form->isValid()) {
        if ($new) {
          $factory = $this->get('security.encoder_factory');
          $encoder = $factory->getEncoder($user);
          $password = $encoder->encodePassword($user->getPassword(), $user->getSalt());
          $user->setPassword($password);
        }

        $em = $this->getDoctrine()->getEntityManager();
        $em->persist($user);
        $em->flush();

        $this->get('session')->setFlash('success', 'User successfully saved');

        return true;
        ;
      }
    }
    return false;
  }

  protected function createDeleteForm($id) {
    return $this->createFormBuilder(array('id' => $id))->add('id', 'hidden')->getForm();
  }

}
