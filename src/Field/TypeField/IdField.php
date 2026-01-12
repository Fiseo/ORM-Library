<?php

namespace OrmLibrary\Field\TypeField;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Field\AbstractField;

class IdField extends AbstractField
{
    protected ?int $value;

    /**
     * Creates an identifier field bound to an entity.
     *
     * The identifier value is immutable once set and cannot be assigned
     * if the associated entity is marked as new.
     *
     * The getter returns null if the identifier has not yet been defined.
     *
     * @param AbstractEntity $entity The entity owning this identifier field.
     *
     * @throws Exception If attempting to set the identifier on a new entity.
     * @throws Exception If attempting to reassign an existing identifier.
     */
    public function __construct(AbstractEntity $entity)
    {
        $setter = function (int $value)  use ($entity) {
            if ($entity->isNew())
                throw new Exception("Can't set the id for a new entity");
            if (isset($this->value))
                throw new Exception("An Id can't be reassigned");
            $this->value = $value;
        };

        parent::__construct("is_int", function () {},null, $setter);
    }
}