<?php

namespace OrmLibrary\Field\TypeField;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Field\AbstractField;

class IdField extends AbstractField
{
    protected ?int $value;

    public function __construct(AbstractEntity $entity)
    {

        $getter = function () {
            if (!isset($this->value))
                return null;
            return $this->value;
        };

        $setter = function (int $value)  use ($entity) {
            if ($entity->isNew())
                throw new Exception("Can't set the id for a new entity");
            if (isset($this->value))
                throw new Exception("An Id can't be reassigned");
            $this->value = $value;
        };

        parent::__construct("is_int", $getter, $setter);
    }
}