<?php

namespace OrmLibrary\Relation;

use Attribute;
use OrmLibrary\Field\AField;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ARelationField extends AField implements IARelation
{
    /**
     * Returns the type of relationship represented by this field.
     *
     * @return ERelation The relation type (Many-To-One)
     */
    public function getType():ERelation {
        return ERelation::MTO;
    }

}