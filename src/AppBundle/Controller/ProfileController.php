<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class ProfileController extends Controller {
    const PasswordMinLength = 8;

    /**
     * @Route("/profile/{username}", name="show_profile")
     */
    public function showAction($username) {
        $user = $this->getDoctrine()->getRepository('AppBundle:User')->findOneByUsername($username);

        if(null === $user) {
            $this->createNotFoundException('The requested user was not found.');
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user
        ]);
    }

    /**
     * @Route("/profile", name="edit_profile")
     */
    public function indexAction(Request $request) {
        $user = $this->getUser();

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
            $user = $em->getRepository('AppBundle:User')->findOneByEmail($email);

            if($user !== null && $user->getId() !== $this->getUser()->getId()) {
                $formProfile->get('email')->addError(new FormError('This E-Mail address is already in use.'));
            } else {
                $user = $this->getUser();
                $user->setEmail($data['email']);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Your profile was updated successfully');

                return $this->redirectToRoute('edit_profile');
            }
        }

        $formPassword->handleRequest($request);

        if($formPassword->isValid()) {
            $data = $formPassword->getData();
            $password = $data['password'];

            $encoder = $this->get('security.password_encoder');
            $encodedPassword = $encoder->encodePassword($user, $password);

            $user = $this->getUser();
            $user->setPassword($encodedPassword);

            $em = $this->getDoctrine()->getManager();

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Your password was changed successfully');

            return $this->redirectToRoute('edit_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'formProfile' => $formProfile->createView(),
            'formPassword' => $formPassword->createView()
        ]);
    }
}