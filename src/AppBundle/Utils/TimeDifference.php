<?php

namespace AppBundle\Utils;

class TimeDifference {
    /**
     * Calculates the difference in minutes
     * between two given DateTimes
     * @param \DateTime $start
     * @param \DateTime $end
     * @return int
     */
    public function getDiffInMinutes(\DateTime $start, \DateTime $end) {
        $diff = $end->diff($start);

        $minutes = 0;
        $minutes += $diff->days * 24 * 60;
        $minutes += $diff->h * 60;
        $minutes += $diff->i;

        return $minutes;
    }
}