<?php

namespace OrmLibrary\Field\TypeField;

use Closure;
use DateTime;
use Exception;
use OrmLibrary\Field\AbstractField;
use OrmLibrary\Helpers;

class DateField extends AbstractField
{
    protected ?DateTime $value = null;

    /**
     * Creates a new DateTime field.
     *
     * A loader callable may be provided to lazily initialize the value
     * when it is first accessed. Custom getter and setter closures
     * can also be supplied to override the default behavior.
     *
     * The value is expected to be an instance of DateTimeInterface.
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
            $setter = function (string|DateTime $value) {
                if ($value instanceof DateTime)
                    $this->value = $value;
                elseif (is_string($value)) {
                    $dt = date_create($value);
                    if ($dt === false)
                        throw new Exception("Invalid date string");
                    $this->value = $dt;
                }
            };
        }

        parent::__construct([Helpers::class, "is_DateTime"], $loader,$getter, $setter);
    }
}