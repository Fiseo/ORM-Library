<?php

namespace OrmLibrary\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AField
{
    private string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    public function getName():string {
        return $this->name;
    }
}