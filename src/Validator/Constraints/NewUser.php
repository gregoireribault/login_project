<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class NewUser extends Constraint
{
    public $message = 'User already exists';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
