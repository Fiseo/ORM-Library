<?php

namespace OrmLibrary\Field\TypeField;

use Closure;
use OrmLibrary\Field\AbstractField;

class StringField extends AbstractField
{
    protected ?string $value;

    public function __construct(callable $loader, ?closure $getter = null, ?closure $setter = null)
    {
        if ($getter == null) {
            $getter =
                function () use ($loader) {
                    if (!isset($this->value))
                        $loader;
                    return $this->value;
                };
        }

        if ($setter == null) {
            $setter =
                function (string $value) {
                    $this->value = $value;
                };
        }
        parent::__construct("is_string", $getter, $setter);
    }
}