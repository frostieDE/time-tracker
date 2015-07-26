<?php

namespace AppBundle\Repository;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityRepository;

class ProjectRepository extends EntityRepository {
    public function findAllProjectsForUser(User $user) {

    }
}