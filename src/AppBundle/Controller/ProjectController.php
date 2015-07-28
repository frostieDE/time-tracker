<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Project;
use AppBundle\Entity\User;
use AppBundle\Entity\WorkedTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;

class ProjectController extends Controller {

    /**
     * @Route("/project/add", name="add_project")
     */
    public function addAction(Request $request) {
        $project = new Project();

        $form = $this->createFormBuilder($project)
            ->add('name', 'text')
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $slugger = $this->get('slugger');

            $slug = $slugger->slugify($project->getName());

            $project->setSlug($slug);

            $em = $this->getDoctrine()->getManager();

            $existingProject = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

            if(null !== $existingProject) {
                $form->get('name')->addError(new FormError('Project name is already in use. Please choose another one'));
            } else {
                $user = $this->getUser();

                $project->setOwner($user);
                $project->addUser($user);

                $user->addProject($project);

                $em->persist($project);
                $em->persist($user);

                $em->flush();

                $this->addFlash('success', 'Project was successfully added');

                return $this->redirectToRoute('show_project', ['slug' => $slug ]);
            }
        }

        return $this->render('project/add.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/project/{slug}", name="show_project")
     */
    public function showAction($slug) {
        $em = $this->getDoctrine()->getManager();
        $project = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

        if($project === null) {
            throw $this->createNotFoundException('The requested project was not found.');
        }

        $this->checkMembership($project, $this->getUser());

        $users = $project->getUsers();

        foreach($users as $user) {
            $user->workedTimeInMinutes = 0;
        }

        $times = $em->getRepository('AppBundle:WorkedTime')->findByProject($project);
        $timedifference = $this->get('timedifference');

        $totalWorkedTime = 0;

        foreach($times as $time) {
            foreach($users as $user) {
                if($user->getId() === $time->getUser()->getId()) {
                    $minutes = $timedifference->getDiffInMinutes($time->getStart(), $time->getEnd());

                    $totalWorkedTime += $minutes;
                    $user->workedTimeInMinutes += $minutes;
                }
            }
        }

        // prepare data for timeline graph
        $lastMonths = 6;
        $data = [];
        $labels = [];

        $currentYear = date('Y');
        $currentMonth = date('m');

        for($i = $lastMonths - 1; $i >= 0; $i--) {
            $start = mktime(0, 0, 0, $currentMonth - $i, 1, $currentYear);
            $end = mktime(0, 0, 0, $currentMonth - $i + 1, 1, $currentYear);

            $labels[] = date('F', $start);

            $workedTime = 0;

            foreach($times as $time) {
                $ts = $time->getStart()->getTimestamp();

                if($start <= $ts && $ts < $end) {
                    $workedTime += $timedifference->getDiffInMinutes($time->getStart(), $time->getEnd());
                }
            }

            $data[] = $workedTime;
        }

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'totalWorkedTime' => $totalWorkedTime,
            'users' => $users,
            'labels' => json_encode($labels),
            'data' => json_encode($data)
        ]);
    }

    /**
     * @Route("/project/{slug}/times/add", name="add_time")
     */
    public function addTimeAction($slug, Request $request) {
        $em = $this->getDoctrine()->getManager();
        $project = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

        if(null === $project) {
            throw $this->createNotFoundException('The requested project was not found.');
        }

        $workedTime = new WorkedTime();
        $workedTime->setStart(new \DateTime());
        $workedTime->setEnd(new \DateTime());

        $form = $this->createFormBuilder($workedTime)
            ->add('start', 'datetime')
            ->add('end', 'datetime')
            ->add('comment')
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $workedTime->setProject($project);
            $workedTime->setUser($this->getUser());

            $em->persist($workedTime);
            $em->flush();

            $this->addFlash('success', 'Time was added successfully');

            return $this->redirectToRoute('show_project', ['slug' => $slug ]);
        }

        return $this->render('project/times/add.html.twig', [
            'project' => $project,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/project/{slug}/times/{id}/edit", name="edit_time")
     */
    public function editTimeAction($slug, $id, Request $request) {
        $em = $this->getDoctrine()->getManager();
        $time = $em->getRepository('AppBundle:WorkedTime')->findOneById($id);

        if(null === $time) {
            throw $this->createNotFoundException('The requested time was not found.');
        }

        if($time->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('You cannot edit the time of another user');
        }

        $form = $this->createFormBuilder($time)
            ->add('start', 'datetime')
            ->add('end', 'datetime')
            ->add('comment')
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $em->persist($time);
            $em->flush();

            $this->addFlash('success', 'Time was updated successfully');

            return $this->redirectToRoute('show_times', ['slug' => $time->getProject()->getSlug() ]);
        }

        return $this->render('project/times/edit.html.twig', [
            'form' => $form->createView(),
            'project' => $time->getProject()
        ]);
    }

    /**
     * @Route("/project/{slug}/times/{id}/delete", name="delete_time")
     */
    public function deleteTimeAction($slug, $id, Request $request) {
        $em = $this->getDoctrine()->getManager();
        $time = $em->getRepository('AppBundle:WorkedTime')->findOneById($id);

        if(null === $time) {
            throw $this->createNotFoundException('The requested time was not found.');
        }

        if($time->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('You cannot delete the time of another user');
        }

        $form = $this->createFormBuilder()
            ->add('confirm', 'checkbox', [ 'required' => true, 'label' => 'Okay, got it!' ])
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $em->remove($time);
            $em->flush();

            $this->addFlash('success', 'Time was successfully removed');
            return $this->redirectToRoute('show_times', [ 'slug' => $time->getProject()->getSlug() ]);
        }

        return $this->render('project/times/delete.html.twig', [
            'form' => $form->createView(),
            'project' => $time->getProject()
        ]);
    }

    /**
     * @Route("/project/{slug}/times/{page}", defaults={"page" = 1}, name="show_times")
     */
    public function showTimesAction($slug, $page) {
        $em = $this->getDoctrine()->getManager();
        $project = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

        if(null === $project) {
            throw $this->createNotFoundException('The requested project was not found.');
        }

        $entriesPerPage = 25;
        $offset = $entriesPerPage * ($page - 1);

        $repository = $this->getDoctrine()->getRepository('AppBundle:WorkedTime');
        $query = $repository->createQueryBuilder('t')
            ->where('t.project = :project')
            ->orderBy('t.start', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($entriesPerPage)
            ->setParameter('project', $project)
            ->getQuery();

        $times = $query->getResult();

        $timedifference = $this->get('timedifference');

        foreach($times as $time) {
            $time->workedTimeInMinutes = $timedifference->getDiffInMinutes($time->getStart(), $time->getEnd());
        }

        $queryCount = $em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from('AppBundle:WorkedTime', 't')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->getQuery();

        $count = $queryCount->getSingleScalarResult();
        $pages = ceil($count / $entriesPerPage);

        return $this->render(':project/times:show.html.twig', [
            'project' => $project,
            'times' => $times,
            'page' => $page,
            'pages' => $pages
        ]);
    }

    /**
     * @Route("/project/{slug}/edit", name="edit_project")
     */
    public function editAction($slug, Request $request) {
        $em = $this->getDoctrine()->getManager();
        $project = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

        if(null === $project) {
            throw $this->createNotFoundException('The requested project was not found.');
        }

        $this->checkOwnership($project, $this->getUser());

        $form = $this->createFormBuilder($project)
            ->add('name', 'text')
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $slugger = $this->get('slugger');

            $slug = $slugger->slugify($project->getName());

            $project->setSlug($slug);

            $em = $this->getDoctrine()->getManager();

            $existingProject = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

            if(null !== $existingProject && $existingProject->getId() !== $project->getId()) {
                $form->get('name')->addError(new FormError('Project name is already in use. Please choose another one'));
            } else {
                $project->setOwner($this->getUser());
                $project->addUser($this->getUser());

                $em->persist($project);
                $em->flush();

                $this->addFlash('success', 'Project was successfully saved');

                return $this->redirectToRoute('show_project', ['slug' => $slug ]);
            }
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/project/{slug}/delete", name="delete_project")
     */
    public function deleteAction($slug, Request $request) {
        $em = $this->getDoctrine()->getManager();
        $project = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

        if(null === $project) {
            throw $this->createNotFoundException('The requested project was not found.');
        }

        $this->checkOwnership($project, $this->getUser());

        $form = $this->createFormBuilder()
            ->add('confirm', 'checkbox', [ 'required' => true, 'label' => 'Okay, got it!' ])
            ->getForm();

        $form->handleRequest($request);

        if($form->isValid()) {
            $em->remove($project);
            $em->flush();

            $this->addFlash('success', 'Project was successfully removed');
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('project/delete.html.twig', [
            'project' => $project,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/project/{slug}/users", name="manage_users")
     */
    public function manageUsersAction($slug, Request $request) {
        $em = $this->getDoctrine()->getManager();
        $project = $em->getRepository('AppBundle:Project')->findOneBySlug($slug);

        if(null === $project) {
            throw $this->createNotFoundException('The requested project was not found.');
        }

        $this->checkOwnership($project, $this->getUser());

        $excludeUsers = [ ];

        foreach($project->getUsers() as $user) {
            $excludeUsers[] = $user->getId();
        }

        $qb = $em->createQueryBuilder();
        $users = $qb ->select('u')
            ->from('AppBundle:User', 'u')
            ->where($qb->expr()->notIn('u.id', $excludeUsers))
            ->getQuery()
            ->getResult();

        if($request->getMethod() === Request::METHOD_POST) {
            $action = $request->request->get('_action', '');
            $csrf = $request->request->get('_csrf_token', '');
            $userId = $request->request->get('_userid', '');

            $user = $em->getRepository('AppBundle:User')->findOneById($userId);

            if(null !== $user && $user->getId() !== $project->getOwner()->getId()) {
                $csrfProvider = $this->get('security.csrf.token_manager');

                if ($action === 'add' && $csrfProvider->isTokenValid(new CsrfToken('user.add', $csrf))) {
                    $project->addUser($user);
                    $user->addProject($project);

                    $em->persist($project);
                    $em->persist($user);

                    $em->flush();

                    $this->addFlash('success', 'Added user to project successfully');
                } else if ($action === 'delete' && $csrfProvider->isTokenValid(new CsrfToken('user.delete', $csrf))) {
                    $project->removeUser($user);
                    $user->removeProject($project);

                    $em->persist($project);
                    $em->persist($user);

                    $em->flush();

                    $this->addFlash('success', 'Added user to project successfully');
                }
            }

            return $this->redirectToRoute('manage_users', [ 'slug' => $project->getSlug() ]);
        }

        return $this->render('project/users.html.twig', [
            'project' => $project,
            'members' => $project->getUsers(),
            'otherUsers' => $users
        ]);
    }

    private function checkOwnership(Project $project, User $user) {
        if($project->getOwner() === null || $project->getOwner()->getId() !== $user->getId()) {
            throw new AccessDeniedException('You must be the owner of the project.');
        }
    }

    private function checkMembership(Project $project, User $user) {
        foreach($project->getUsers() as $member) {
            if($member->getId() === $user->getId()) {
                return; // fine, you belong to this project
            }
        }

        throw new AccessDeniedException('You must be part of the project.');
    }


}