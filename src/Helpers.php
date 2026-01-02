<?php
namespace OrmLibrary;

use DateTime;
use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Entity\EntityRepository;

/**
 * Utility class providing validation and helper methods for entities and repositories.
 *
 * This class cannot be instantiated and only exposes static methods.
 */
class Helpers {
    private function __construct() {}

    /**
     * Validates that a value is either an instance of the specified entity class and saved,
     * or an integer (representing a potential ID).
     *
     * @param mixed $value The value to validate.
     * @param string $fqcn Fully qualified class name of the expected entity type.
     *
     * @return bool True if $value is a saved entity instance or an integer ID, false otherwise.
     */
    static public function newClassValidator($value, string $fqcn):bool {
        if ($value instanceof $fqcn && !$value->isNew())
            return true;
        elseif (is_int($value))
            return true;
        else
            return false;
    }

    /**
     * Validates that a value is either an instance of the specified class, or an integer.
     *
     * @param mixed $value The value to validate.
     * @param string $fqcn Fully qualified class name of the expected type.
     *
     * @return bool True if $value matches the expected type or is an integer, false otherwise.
     */
    static public function classValidator($value, string $fqcn):bool {
        if ($value instanceof $fqcn)
            return true;
        elseif (is_int($value))
            return true;
        else
            return false;
    }

    /**
     * Checks if a given class is a subclass of AbstractEntity.
     *
     * @param string $fqcn Fully qualified class name.
     *
     * @return bool True if the class is a child of AbstractEntity, false otherwise.
     */
    static public function isEntity(string $fqcn):bool {
        return is_subclass_of($fqcn, AbstractEntity::class);
    }


    /**
     * Checks if a given class is a subclass of EntityRepository.
     *
     * @param string $fqcn Fully qualified class name.
     *
     * @return bool True if the class is a child of EntityRepository, false otherwise.
     */
    static public function isRepository(string $fqcn):bool {
        return is_subclass_of($fqcn, EntityRepository::class);
    }

    /**
     * Validates that a value is either a DateTime instance or a string that could represent a date.
     *
     * @param mixed $value The value to validate.
     *
     * @return bool True if the value is a DateTime or a string, false otherwise.
     */
    static public function is_DateTime($value):bool {
        if ($value instanceof DateTime)
            return true;
        elseif (is_string($value))
            return true;
        else
            return false;
    }
}








