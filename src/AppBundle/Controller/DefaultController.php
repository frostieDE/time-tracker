<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction() {
        return $this->redirectToRoute('dashboard');
    }

    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboardAction() {
        if(!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();
        $projects = $user->getProjects();
        $times = $em->getRepository('AppBundle:WorkedTime')->findByUser($user);

        $timedifference = $this->get('timedifference');

        $totalWorkedTimeInMinutes = 0;

        foreach($projects as $project) {
            $project->workedTimeInMinutes = 0;
        }

        foreach($times as $time) {
            $diff = $timedifference->getDiffInMinutes($time->getStart(), $time->getEnd());

            $totalWorkedTimeInMinutes += $diff;

            foreach($projects as $project) {
                if($project->getId() === $time->getProject()->getId()) {
                    $project->workedTimeInMinutes += $diff;
                }
            }
        }

        return $this->render('index/index.html.twig', [
            'projects' => $projects,
            'totalWorkedTime' => $totalWorkedTimeInMinutes
        ]);
    }
}
