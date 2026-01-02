<?php

namespace OrmLibrary\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AField
{
    /** @var string Database field name */
    private string $name;

    /** @var bool Indicates whether the field allows NULL values */
    private bool $nullable;

    /**
     * Creates a new field mapping attribute.
     *
     * @param string $name     The database column name.
     * @param bool   $nullable Whether the field allows NULL values.
     */
    public function __construct(string $name, bool $nullable) {
        $this->name = $name;
        $this->nullable = $nullable;
    }

    /**
     * Returns the database column name.
     *
     * @return string The field name.
     */
    public function getName():string {
        return $this->name;
    }

    /**
     * Indicates whether the field allows NULL values.
     *
     * @return bool True if the field is nullable, false otherwise.
     */
    public function isNullable():bool {
        return $this->nullable;
    }
}