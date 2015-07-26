<?php

namespace AppBundle\Twig;

class TimeDifference extends \Twig_Extension {

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
        if(!is_numeric($difference)) {
            return $difference;
        }

        $minutes = $difference % 60;
        $hours = floor($difference / 60);
        $days = floor($difference / (60* 24));

        $result = '';

        if($days > 0) {
            $result .= $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ';
        }

        if($hours > 0) {
            $result .= $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ';
        }

        $result .= $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes');

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