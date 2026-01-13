<?php

namespace OrmLibrary\Relation;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Field\AbstractField;
use OrmLibrary\Helpers;

class EntityField extends AbstractField
{
    /** @var AbstractEntity|null The entity instance stored in this field. */
    protected ?AbstractEntity $value = null;

    /** @var string The fully qualified class name of the related entity. */
    private string $entityClass;

    /** @var string Error message thrown when setting a value of the wrong type or an unsaved entity. */
    protected string $setterErrorMessage = "Wrong type of Entity or unsaved Entity";

    /**
     * Constructs a relation field.
     *
     * @param string $entityClass The class name of the related entity (must extend AbstractEntity)
     * @param callable $loader Callable used for lazy-loading the entity instance
     *
     * @throws Exception If the provided class is not a child of AbstractEntity
     */
    public function __construct(string $entityClass, callable $loader)
    {
        if (!Helpers::isEntity($entityClass))
            throw new Exception("A relation must be with a child entity of AbstractEntity class");
        $this->entityClass = $entityClass;

        $getter = function () use ($loader) {
            if (!isset($this->value)) {
                try {
                    ($loader)();
                } catch (Exception $e) {}
            }
            return $this->value;
        };

        $setter = function (AbstractEntity|int $value) {
            if (is_int($value))
                $this->value = new $this->entityClass($value);
            else
                $this->value = $value;
        };

        parent::__construct([$this,"validator"], $loader, $getter, $setter);
    }

    public function __debugInfo():?array
    {

        return [
            'Value' => $this->get(),
        ];
    }

    /**
     * Validates that a value assigned to this field is a proper entity instance.
     *
     * @param  mixed $value The value to validate
     * @return bool True if the value is an instance of the correct entity class
     */
    public function validator(mixed $value):bool {
        return Helpers::newClassValidator($value, $this->entityClass);
    }

    /**
     * Returns the ID of the related entity.
     *
     * @return int|null The ID of the entity, or null if not set
     *
     * @throws Exception
     */
    public function id():?int {
        if (is_null($this->value))
            return null;
        return $this->value->id->get();
    }

    /**
     * Loads the related entity from the database if it is set.
     *
     * @return void
     */
    public function load():void {
        if (is_null($this->value))
            return;
        try {
            $this->value->load();
        } catch (Exception $e) {}
    }
}