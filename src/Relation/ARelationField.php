<?php

namespace OrmLibrary\Relation;

use Attribute;
use OrmLibrary\Field\AField;
use OrmLibrary\Field\IField;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ARelationField extends AField implements IARelation
{
    public function getType():ERelation {
        return ERelation::MTO;
    }

}