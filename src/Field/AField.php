<?php

namespace OrmLibrary\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AField
{
    private string $name;
    private bool $nullable;

    public function __construct(string $name, string $nullable) {
        $this->name = $name;
        $this->nullable = $nullable;
    }

    public function getName():string {
        return $this->name;
    }

    public function isNullable():bool {
        return $this->nullable;
    }
}