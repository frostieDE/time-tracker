<?php

namespace AppBundle\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class StartBeforeEndConstraint extends Constraint {

    public $message = 'Start time must be before end time';

    public function getTargets() {
        return self::CLASS_CONSTRAINT;
    }
}