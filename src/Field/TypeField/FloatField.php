<?php

namespace OrmLibrary\Field\TypeField;

use Closure;
use Exception;
use OrmLibrary\Field\AbstractField;

class FloatField extends AbstractField
{
    protected ?float $value;

    /**
     * Creates a new float field.
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

        if ($getter === null) {
            $getter = function () use ($loader) {
                if (!isset($this->value)) {
                    try {
                        ($loader)();
                    } catch (Exception $e) {}
                }
                return $this->value;
            };
        }

        if ($setter === null) {
            $setter = function (float $value) {
                $this->value = $value;
            };
        }

        parent::__construct("is_float", $getter, $setter);
    }
}