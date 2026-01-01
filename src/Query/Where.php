<?php


namespace OrmLibrary\Query;

use Exception;
use OrmLibrary\Entity\EntityRepository;

class Where
{
    private string $entity;
    private string $field;
    private mixed $value;
    private bool $equal = true;
    private bool $list;
    private bool $like = false;
    private string $salt;

    public function __construct()
    {
        $this->salt = bin2hex(random_bytes(16));
    }

    private function hasEntity(): bool
    {
        return !empty($this->entity);
    }

    private function ready2Use(): bool
    {
        if (!empty($this->value) && !empty($this->field)) {
            return true;
        }
        return false;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->reset();
        if (EntityRepository::doEntityExist($entity))
            $this->entity = $entity;
        else
            throw new Exception("La table $entity n'existe pas.");
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        if (!$this->hasEntity())
            throw new Exception("La table n'a pas été défini.");

        $this->field = EntityRepository::getField($field, $this->entity);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        if (is_array($value))
            $this->list = true;
        else
            $this->list = false;
        $this->value = $value;
    }

    public function invertEqual(): bool
    {
        $this->equal = !$this->equal;
        return $this->equal;
    }

    public function invertLike(): bool
    {
        $this->like = !$this->like;
        return $this->like;
    }

    public function getQuery(): string
    {
        if (!$this->ready2Use())
            throw new Exception("Toutes les valeurs nécessaires n'ont pas été défini.");

        $query = $this->getEntity() . "." . $this->field . " ";

        if ($this->list) {
            if ($this->equal)
                $query .= "IN (";
            else
                $query .= "NOT IN (";

            foreach ($this->value as $key => $value) {
                $query .= ":" . $this->field . $key;
                if ($key != array_key_last($this->value)) {
                    $query .= ", ";
                }
            }
            return $query . ")";
        } elseif ($this->like) {
            if ($this->equal)
                $query .= "LIKE ";
            else
                $query .= "NOT LIKE ";

            return $query . ":" . $this->field . $this->salt;
        } else {
            if ($this->equal) {
                $query .= "= ";
            } else {
                $query .= "!= ";
            }
            return $query . ":" . $this->field . $this->salt;
        }

    }

    public function doBindValue(\PDOStatement $pdo): void
    {
        if (!$this->ready2Use())
            throw new Exception("Toutes les valeurs nécessaires n'ont pas été défini.");

        if ($this->list) {
            foreach ($this->value as $key => $value) {
                $pdo->bindValue(":" . $this->field . $key, $value);
            }
        } elseif ($this->like) {
            $pdo->bindValue(":" . $this->field . $this->salt, '%' . $this->value . '%');
        } else {
            $pdo->bindValue(":" . $this->field . $this->salt, $this->value);
        }
    }

    public function reset(): void
    {
        unset($this->entity);
        unset($this->field);
        unset($this->value);
        unset($this->list);
        $this->like = false;
        $this->equal = true;
        $this->salt = bin2hex(random_bytes(16));
    }

    public static function builder():WhereBuilder {
        return new WhereBuilder();
    }
}

class WhereBuilder {
    private string $entity;
    private string $field;
    private mixed $value;

    public function entity(string $user):WhereBuilder {
        $this->entity = $user;
        return $this;
    }
    public function field(string $field):WhereBuilder {
        $this->field = $field;
        return $this;
    }
    public function value($value):WhereBuilder {
        $this->value = $value;
        return $this;
    }

    public function build():Where {
        $w = new Where();
        $w->setEntity($this->entity);
        $w->setField($this->field);
        $w->setValue($this->value);
        return $w;
    }
}