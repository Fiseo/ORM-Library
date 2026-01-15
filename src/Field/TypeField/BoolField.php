<?php
namespace OrmLibrary\Field\TypeField;


use Closure;
use Exception;
use OrmLibrary\Field\AbstractField;


class BoolField extends AbstractField
{
    protected ?bool $value;

    /**
     * Creates a new boolean field.
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
    public function __construct(callable $loader, ?closure $getter = null, ?closure $setter = null) {
        if ($setter === null) {
            $setter = function(bool $value) {
                $this->value = $value;
            };
            $this->defaultSetter = true;
        }

        parent::__construct('is_bool', $loader, $getter, $setter);
    }
}

