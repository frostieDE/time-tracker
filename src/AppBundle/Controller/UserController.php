<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class UserController extends Controller {
    const PasswordMinLength = 8;

    /**
     * @Route("/admin/users", name="show_users")
     */
    public function indexAction() {
        $em = $this->getDoctrine()->getManager();

        $users = $em->getRepository('AppBundle:User')->findAll();

        return $this->render('users/index.html.twig', [
            'users' => $users
        ]);
    }

    /**
     * @Route("/admin/users/add", name="add_user")
     */
    public function addAction(Request $request) {
        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('username', 'text', [
                'label' => 'form.username'
            ])
            ->add('password', 'repeated', [
                'type' => 'password',
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => static::PasswordMinLength])
                ],
                'required' => true,
                'invalid_message' => $this->get('translator')->trans('form.password_match'),
                'first_options' => [
                    'label' => 'form.password'
                ],
                'second_options' => [
                    'label' => 'form.confirm_password'
                ]
            ])
            ->add('email', 'email', [
                'label' => 'form.email',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Email()
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $existingUser = $em->getRepository('AppBundle:User')->findOneByUsername($user->getUsername());

            if(null !== $existingUser) {
                $form->get('username')->addError(new FormError($this->get('translator')->trans('error.username_already_in_use')));
            } else {
                $existingUser = $em->getRepository('AppBundle:User')->findOneByEmail($user->getEmail());

                if(null !== $existingUser) {
                    $form->get('email')->addError(new FormError($this->get('translator')->trans('error.mail_already_used')));
                } else {
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'users.add.success');

                    return $this->redirectToRoute('show_users');
                }
            }
        }

        return $this->render('users/add.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/admin/users/{username}/edit", name="edit_user")
     */
    public function editAction($username, Request $request) {
        $user = $this->getDoctrine()->getRepository('AppBundle:User')->findOneByUsername($username);

        if($user === null) {
            throw $this->createNotFoundException('The requested user does not exist.');
        }

        $defaultData = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail()
        ];

        $formProfile = $this->createFormBuilder($defaultData)
            ->add('username', 'text', [
                'read_only' => true,
                'label' => 'form.username'
            ])
            ->add('email', 'email', [
                'label' => 'form.email',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Email()
                ]
            ])
            ->getForm();

        $formPassword = $this->createFormBuilder()
            ->add('password', 'repeated', [
                'type' => 'password',
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => static::PasswordMinLength])
                ],
                'required' => true,
                'invalid_message' => $this->get('translator')->trans('form.password_match'),
                'first_options' => [
                    'label' => 'form.password'
                ],
                'second_options' => [
                    'label' => 'form.confirm_password'
                ]
            ])
            ->getForm();

        $formProfile->handleRequest($request);

        if($formProfile->isValid()) {
            $data = $formProfile->getData();
            $email = $data['email'];

            $em = $this->getDoctrine()->getManager();
            $existingUser = $em->getRepository('AppBundle:User')->findOneByEmail($email);

            if($existingUser !== null && $existingUser->getId() !== $user->getId()) {
                $formProfile->get('email')->addError(new FormError($this->get('translator')->trans('error.mail_already_in_use')));
            } else {
                $user->setEmail($data['email']);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'users.edit.success');

                return $this->redirectToRoute('edit_user', [ 'username' => $user->getUsername() ]);
            }
        }

        $formPassword->handleRequest($request);

        if($formPassword->isValid()) {
            $data = $formPassword->getData();
            $password = $data['password'];

            $encoder = $this->get('security.password_encoder');
            $encodedPassword = $encoder->encodePassword($user, $password);

            $user->setPassword($encodedPassword);

            $em = $this->getDoctrine()->getManager();

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'users.edit.success');

            return $this->redirectToRoute('edit_user', [ 'username' => $user->getUsername() ]);
        }

        return $this->render('users/edit.html.twig', [
            'formProfile' => $formProfile->createView(),
            'formPassword' => $formPassword->createView()
        ]);
    }

    /**
     * @Route("/admin/users/{username}/delete", name="delete_user")
     */
    public function deleteAction($username, Request $request) {
        $user = $this->getDoctrine()->getRepository('AppBundle:User')->findOneByUsername($username);

        if($user === null) {
            throw $this->createNotFoundException('The requested user does not exist.');
        }

        if($user->getId() === 1) {
            $this->addFlash('alert', 'users.delete.error.id_one');
            $this->redirectToRoute('show_users');
        } else if($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('alert', 'users.delete.error.yourself');
            $this->redirectToRoute('show_users');
        }

        $form = $this->createFormBuilder()
            ->add('confirm', 'checkbox', [
                'required' => true,
                'label' => 'form.got_it' ])
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $em->remove($user);
            $em->flush();

            $this->addFlash('success', 'users.delete.success');
            return $this->redirectToRoute('show_users');
        }

        return $this->render('users/delete.html.twig', [
            'form' => $form->createView()
        ]);
    }
}