<?php
namespace OrmLibrary;

use Exception;
use OrmLibrary\Entity\AbstractEntity;

class helpers {
    private function __construct() {}

    public static function getEntities(AbstractEntity $from, AbstractEntity $to):array {
        throw new Exception("Not implemented");
    }
}








