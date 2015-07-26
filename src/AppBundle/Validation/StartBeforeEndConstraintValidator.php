<?php

namespace AppBundle\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class StartBeforeEndConstraintValidator extends ConstraintValidator {

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @api
     */
    public function validate($time, Constraint $constraint)
    {
        if($time->getStart() >= $time->getEnd()) {
            $this->context->buildViolation($constraint->message)->atPath('end')->addViolation();
        }
    }
}