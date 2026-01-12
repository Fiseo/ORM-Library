<?php

namespace OrmLibrary\Field\TypeField;

use Closure;
use Exception;
use OrmLibrary\Field\AbstractField;

class IntField extends AbstractField
{
    protected ?int $value;

    /**
     * Creates a new integer field.
     *
     * A loader callable may be provided to lazily initialize the value
     * when it is first accessed. Custom getter and setter closures
     * can also be supplied to override the default behavior.
     *
     * @param callable      $loader Callable responsible for loading the value.
     * @param Closure|null  $getter Optional custom getter closure.
     * @param Closure|null  $setter Optional custom setter closure.
     *
     * @throws Exception If the loader callable fails during execution.
     */
    public function __construct(callable $loader, ?closure $getter = null, ?closure $setter = null)
    {
        if ($setter === null) {
            $setter = function (int $value) {
                $this->value = $value;
            };
        }

        parent::__construct("is_int", $loader, $getter, $setter);
    }
}

