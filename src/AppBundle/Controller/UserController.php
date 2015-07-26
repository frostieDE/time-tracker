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
            ->add('username')
            ->add('password', 'repeated', [
                'type' => 'password',
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => static::PasswordMinLength])
                ],
                'required' => true,
                'invalid_message' => 'The password fields must match',
                'first_options' => [
                    'label' => 'Password'
                ],
                'second_options' => [
                    'label' => 'Repeat password'
                ]
            ])
            ->add('email')
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $existingUser = $em->getRepository('AppBundle:User')->findOneByUsername($user->getUsername());

            if(null !== $existingUser) {
                $form->get('username')->addError(new FormError('Username is aleady in use'));
            } else {
                $existingUser = $em->getRepository('AppBundle:User')->findOneByEmail($user->getEmail());

                if(null !== $existingUser) {
                    $form->get('email')->addError(new FormError('E-Mail address is already in use'));
                } else {
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'User added successfully');

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
            $this->createNotFoundException('The requested user does not exist.');
        }

        $defaultData = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail()
        ];

        $formProfile = $this->createFormBuilder($defaultData)
            ->add('username', 'text', [ 'read_only' => true ])
            ->add('email', 'email', [
                'label' => 'E-Mail',
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
                'invalid_message' => 'The password fields must match',
                'first_options' => [
                    'label' => 'Password'
                ],
                'second_options' => [
                    'label' => 'Repeat password'
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
                $formProfile->get('email')->addError(new FormError('This E-Mail address is already in use.'));
            } else {
                $user->setEmail($data['email']);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'User was successfully updated');

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

            $this->addFlash('success', 'Password was successfully changed');

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
            $this->createNotFoundException('The requested user does not exist.');
        }

        if($user->getId() === 1) {
            $this->addFlash('alert', 'You cannot remove user with the ID 1.');
            $this->redirectToRoute('show_users');
        } else if($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('alert', 'You cannot remove yourself.');
            $this->redirectToRoute('show_users');
        }

        $form = $this->createFormBuilder()
            ->add('confirm', 'checkbox', [ 'required' => true, 'label' => 'Okay, got it!' ])
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $em->remove($user);
            $em->flush();

            $this->addFlash('success', 'User was successfully deleted');
            return $this->redirectToRoute('show_users');
        }

        return $this->render('users/delete.html.twig', [
            'form' => $form->createView()
        ]);
    }
}