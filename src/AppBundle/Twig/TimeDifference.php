<?php

namespace AppBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;

class TimeDifference extends \Twig_Extension {

    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function getFilters() {
        return [
            new \Twig_SimpleFilter('timedifference', [ $this, 'renderTimeDifference' ])
        ];
    }

    /**
     * @param int $difference
     * @return string
     */
    public function renderTimeDifference($difference) {
        $translator = $this->container->get('translator');

        if(!is_numeric($difference)) {
            return $difference;
        }

        $minutes = $difference % 60;
        $hours = floor($difference / 60);
        $days = floor($difference / (60* 24));

        $result = '';

        if($days > 0) {
            $result .= $days . ' ' . ($days == 1 ? $translator->trans('time_difference.day') : $translator->trans('time_difference.days')) . ' ';
        }

        if($hours > 0) {
            $result .= $hours . ' ' . ($hours == 1 ? $translator->trans('time_difference.hour') : $translator->trans('time_difference.hours')) . ' ';
        }

        $result .= $minutes . ' ' . ($minutes == 1 ? $translator->trans('time_difference.minute') : $translator->trans('time_difference.minutes'));

        return $result;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName() {
        return 'timedifference';
    }
}