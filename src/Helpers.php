<?php
namespace OrmLibrary;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Entity\EntityRepository;

class Helpers {
    private function __construct() {}

    static public function newClassValidator($value, string $fqcn):bool {
        if ($value instanceof $fqcn && !$value->isNew())
            return true;
        elseif (is_int($value))
            return true;
        else
            return false;
    }

    static public function classValidator($value, string $fqcn):bool {
        if ($value instanceof $fqcn)
            return true;
        elseif (is_int($value))
            return true;
        else
            return false;
    }

    static public function isEntity(string $fqcn):bool {
        return is_subclass_of($fqcn, AbstractEntity::class);
    }

    static public function isRepository(string $fqcn):bool {
        return is_subclass_of($fqcn, EntityRepository::class);
    }
}








