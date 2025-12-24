<?php

namespace OrmLibrary\Relation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ARelation implements IARelation
{
    private ERelation $type;

    public function __construct(Erelation $type) {
        $this->type = $type;
    }
    public function getType():ERelation {
        return $this->type;
    }

}