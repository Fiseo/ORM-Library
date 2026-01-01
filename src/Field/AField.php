<?php

namespace OrmLibrary\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AField
{
    private string $name;
    private bool $nullable;

    public function __construct(string $name, bool $nullable) {
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