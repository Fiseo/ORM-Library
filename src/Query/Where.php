<?php


namespace OrmLibrary\Query;

use Exception;
use OrmLibrary\Entity\EntityRepository;
use PDOStatement;

class Where
{
    private string $entity;
    private string $field;
    private mixed $value;
    private bool $equal = true;
    private bool $list;
    private bool $like = false;
    private string $salt;

    /**
     * Initializes a new WHERE condition instance.
     *
     * A random salt is generated to ensure unique parameter names.
     *
     * @throws Exception If cryptographically secure random bytes cannot be generated.
     */
    public function __construct()
    {
        $this->salt = bin2hex(random_bytes(16));
    }

    /**
     * Checks whether an entity has been defined for this WHERE condition.
     *
     * @return bool True if an entity is defined, false otherwise.
     */
    private function hasEntity(): bool
    {
        return !empty($this->entity);
    }

    /**
     * Determines whether the WHERE condition is fully defined.
     *
     * A condition is considered ready when both field and value
     * have been provided.
     *
     * @return bool True if the condition can be converted to SQL, false otherwise.
     */
    private function ready2Use(): bool
    {
        if (!empty($this->value) && !empty($this->field)) {
            return true;
        }
        return false;
    }

    //region getter and setter

    /**
     * Returns the entity associated with this WHERE condition.
     *
     * @return string The entity name.
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * Sets the entity for the WHERE condition.
     *
     * Resets the current condition state before assigning the entity.
     * The entity must exist in the ORM metadata.
     *
     * @param string $entity The entity (table) name.
     *
     * @return void
     *
     * @throws Exception If the entity does not exist.
     */
    public function setEntity(string $entity): void
    {
        $this->reset();
        if (EntityRepository::doEntityExist($entity))
            $this->entity = $entity;
        else
            throw new Exception("La table $entity n'existe pas.");
    }

    /**
     * Returns the field name used in the WHERE condition.
     *
     * @return string The field name.
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Sets the field for the WHERE condition.
     *
     * The entity must be defined before calling this method.
     *
     * @param string $field The field name.
     *
     * @return void
     *
     * @throws Exception If the entity is not defined.
     * @throws Exception If the field does not exist in the entity.
     */
    public function setField(string $field): void
    {
        if (!$this->hasEntity())
            throw new Exception("La table n'a pas été défini.");

        $this->field = EntityRepository::getField($field, $this->entity);
    }

    /**
     * Returns the value used in the WHERE condition.
     *
     * @return mixed The condition value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Sets the value for the WHERE condition.
     *
     * If the value is an array, the condition will be treated
     * as an IN / NOT IN clause.
     *
     * @param mixed $value The value or list of values.
     *
     * @return void
     */
    public function setValue(mixed $value): void
    {
        if (is_array($value))
            $this->list = true;
        else
            $this->list = false;
        $this->value = $value;
    }

    /**
     * Toggles the comparison operator between equality and inequality.
     *
     * - "=" ↔ "!="
     * - "IN" ↔ "NOT IN"
     * - "LIKE" ↔ "NOT LIKE"
     *
     * @return bool The new equality state.
     */
    public function invertEqual(): bool
    {
        $this->equal = !$this->equal;
        return $this->equal;
    }

    /**
     * Toggles LIKE comparison mode.
     *
     * When enabled, the condition will use LIKE / NOT LIKE
     * instead of equality comparison.
     *
     * @return bool The new LIKE state.
     */
    public function invertLike(): bool
    {
        $this->like = !$this->like;
        return $this->like;
    }
    //endregion

    /**
     * Generates the SQL fragment for this WHERE condition.
     *
     * @return string The SQL WHERE fragment (without the WHERE keyword).
     *
     * @throws Exception If the condition is not fully defined.
     */
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

    /**
     * Binds values required by this WHERE condition to a PDO statement.
     *
     * Parameter names are generated dynamically to avoid collisions
     * when multiple conditions target the same field.
     *
     * @param PDOStatement $pdo The prepared PDO statement.
     *
     * @return void
     *
     * @throws Exception If the condition is not fully defined.
     */
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

    /**
     * Resets the WHERE condition to its initial state.
     *
     * Clears entity, field, value and operator flags,
     * and regenerates a new parameter salt.
     *
     * @return void
     *
     * @throws Exception If cryptographically secure random bytes cannot be generated.
     */
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

    /**
     * Creates a new WhereBuilder instance.
     *
     * @return WhereBuilder A new builder instance.
     */
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