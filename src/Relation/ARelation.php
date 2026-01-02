<?php

namespace OrmLibrary\Relation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ARelation implements IARelation
{
    /** @var ERelation Relation type associated with the property.*/
    private ERelation $type;


    /**
     * Creates a relation attribute with a specific relation type.
     *
     * @param ERelation $type The relation type of the property
     */
    public function __construct(Erelation $type) {
        $this->type = $type;
    }

    /**
     * Returns the relation type of the property.
     *
     * @return ERelation
     */
    public function getType():ERelation {
        return $this->type;
    }
}