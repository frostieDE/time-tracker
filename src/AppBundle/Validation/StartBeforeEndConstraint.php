<?php

namespace AppBundle\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class StartBeforeEndConstraint extends Constraint {

    public $message = 'startbeforeend';

    public function getTargets() {
        return self::CLASS_CONSTRAINT;
    }
}