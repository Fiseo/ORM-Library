<?php

namespace OrmLibrary\Relation;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Field\AbstractField;
use OrmLibrary\Helpers;

class EntityField extends AbstractField
{
    protected ?AbstractEntity $value = null;
    private string $entityClass;
    protected string $setterErrorMessage = "Wrong type of Entity or unsaved Entity";

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

        parent::__construct([$this,"validator"], $getter, $setter);
    }

    public function validator($value):bool {
        return Helpers::newClassValidator($value, $this->entityClass);
    }

    public function id():?int {
        if (is_null($this->value))
            return null;
        return $this->value->id->get();
    }
    public function load():void {
        if (is_null($this->value))
            return;
        $this->value->load();
    }
}