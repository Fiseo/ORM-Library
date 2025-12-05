<?php

use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Entity\EntityRepository;
use OrmLibrary\Query\Join;

function getEntityRepository(string $name):EntityRepository {
    if (!EntityRepository::doEntityExist(ucfirst($name)))
        throw new \Exception("Entity $name does not exist");
    $entityClass = "\\Virgile\\HomemadeOrm\\Entity\\" . ucfirst($name) . "Repository";
    return new $entityClass();
}

function getEntity(string $name):AbstractEntity {
    if (!EntityRepository::doEntityExist(ucfirst($name)))
        throw new \Exception("Entity $name does not exist");
    $name = ucfirst("$name");
    return new $name();
}
function getEntities(AbstractEntity $from, AbstractEntity $to):array {
    throw new Exception("Not implemented");
}



