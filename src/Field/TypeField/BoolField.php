<?php
namespace OrmLibrary\Field\TypeField;


use Closure;
use DateTime;
use Exception;
use OrmLibrary\Field\AbstractField;


class BoolField extends AbstractField
{
    protected ?bool $value;

    public function __construct(callable $loader, ?closure $getter = null, ?closure $setter = null) {

        if ($getter === null) {
            $getter = function() use ($loader) {
                if (!isset($this->value)) {
                    try {
                        ($loader)();
                    } catch (Exception $e) {}
                }
                return $this->value;
            };
        }

        if ($setter === null) {
            $setter = function(bool $value) {
                $this->value = $value;
            };
        }

        parent::__construct('is_bool', $getter, $setter);
    }
}

