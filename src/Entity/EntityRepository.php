<?php

namespace OrmLibrary\Entity;

use Exception;
use OrmLibrary\Query\Join;
use OrmLibrary\Query\Where;

abstract class EntityRepository
{
    protected static array $fields;
    protected static string $entity;
    protected static array $entitylinked;

    static public function doEntityExist(string $entity): bool
    {
        if (in_array($entity, include "../listTable.php")) {
            return true;
        }
        return false;
    }

    static public function hasField(string $field): bool
    {
        if (in_array($field, static::$fields)) {
            return true;
        }
        return false;
    }

    static public function getName(): string
    {
        return static::$entity;
    }

    static public function isLinked(string $entity): bool
    {
        $entityLinked = [];
        foreach (static::$entitylinked as $linkedEntity => $field) {
            $entityLinked[] = $linkedEntity;
        }
        if (in_array($entity, $entityLinked)) {
            return true;
        }
        return false;
    }

    static public function getLink(string $entity): array
    {
        $result = [];
        foreach (static::$entitylinked as $linkedEntity => $field) {
            if ($linkedEntity == $entity) {
                $result[$linkedEntity] = $field;
                break;
            }
        }
        if (empty($result)) {
            throw new Exception("No linked entity found for $entity");
        } else {
            return $result;
        }
    }

    private function verifyValues(array $values): void
    {
        foreach ($values as $field => $value) {
            if (!static::hasField($field)) {
                throw new Exception("Field '$field' does not exist");
            }
        }
    }

    private function verifyFields(array $allFields, array $entityAvailable): void
    {
        foreach ($allFields as $entity => $fields) {
            if (!static::doEntityExist($entity))
                throw new Exception("Entity '$entity' does not exist");

            if (!in_array($entity, $entityAvailable))
                throw new Exception("Entity '$entity' is not available in the current context");

            $entityRepository = "\\Repository\\" . $entity . "Repository";
            foreach ($fields as $field) {
                if (!(new $entityRepository())::hasField($field))
                    throw new Exception("Field '$field' does not exist in entity '$entity'");
            }
        }
    }

    private function verifyJoins(array|Join $joins, array &$entityAvailable): void
    {
        if (is_array($joins)) {
            foreach ($joins as $join) {
                $join->verify($entityAvailable);
            }
        } else
            $joins->verify($entityAvailable);
    }

    private function getQueryInsert(array $values): string
    {

        $query = "INSERT INTO " . static::$entity . " (";

        foreach ($values as $field => $value) {
            $query .= $field;
            if ($field != array_key_last($values)) {
                $query .= ', ';
            } else {
                $query .= ') VALUE (';
            }
        }

        foreach ($values as $field => $value) {
            $query .= ":" . $field;
            if ($field != array_key_last($values)) {
                $query .= ', ';
            } else {
                $query .= ');';
            }
        }

        return $query;
    }

    private function getQueryUpdate(array $values): string
    {

        $query = "UPDATE " . static::$entity . " SET ";

        foreach ($values as $field => $value) {
            $query .= $field . " = :" . $field;

            if ($field != array_key_last($values))
                $query .= ", ";
            else
                $query .= " ";

        }
        return $query;
    }

    private function getQueryDelete(): string
    {
        return "DELETE FROM " . static::$entity . " ";
    }

    private function getQuerySelect(array $allFields): string
    {
        $query = "SELECT ";
        foreach ($allFields as $entity => $fields) {
            foreach ($fields as $key => $field) {
                $query .= $entity . "." . $field;

                if ($entity == array_key_last($allFields) && $key == array_key_last($fields))
                    $query = $query . " FROM " . static::$entity;
                else
                    $query = $query . ", ";
            }
        }
        return $query;
    }

    private function getQueryWhere(array|Where $wheres): string
    {
        $query = "WHERE ";

        if (is_array($wheres)) {
            foreach ($wheres as $key => $where) {
                $query .= $where->getQuery();
                if ($key != array_key_last($wheres)) {
                    $query .= " AND ";
                }
            }
        } else {
            $query .= $wheres->getQuery();
        }
        return $query;
    }

    private function getQueryJoin(array|Join $joins): string
    {
        $query = " ";
        if (is_array($joins)) {
            foreach ($joins as $join) {
                $query .= $join->getQuery() . " ";
            }
        } else {
            $query .= $joins->getQuery() . " ";
        }
        return $query;
    }

    private function bindValues(PDOStatement $statement, array $values): void
    {
        foreach ($values as $field => $value) {
            $statement->bindValue(":" . $field, $value);
        }
    }

    protected function bindWhereValues(PDOStatement $statement, array|Where $wheres): void
    {
        if (is_array($wheres)) {
            foreach ($wheres as $where) {
                $where->doBindValue($statement);
            }
        } else {
            $wheres->doBindValue($statement);
        }
    }

    public function insert(array $values): int
    {
        $this->verifyValues($values);
        $sql = $this->getQueryInsert($values);
        $pdo = (new LoginServer())->getPDO();
        $statement = $pdo->prepare($sql);
        $this->bindValues($statement, $values);
        $statement->execute();
        return $pdo->lastInsertId();
    }

    public function update(array $values, Where|array $wheres): void
    {
        $this->verifyValues($values);
        $sql = $this->getQueryUpdate($values);
        $sql .= $this->getQueryWhere($wheres);
        $statement = (new LoginServer())->getPDO()->prepare($sql);
        $this->bindValues($statement, $values);
        $this->bindWhereValues($statement, $wheres);
        $statement->execute();
    }

    public function delete(Where|array $wheres): void
    {
        $sql = $this->getQueryDelete();
        $sql .= $this->getQueryWhere($wheres);
        $statement = (new LoginServer())->getPDO()->prepare($sql);
        $this->bindWhereValues($statement, $wheres);
        $statement->execute();
    }

    public function select(array $fields, array|Join|null $joins = null, array|Where|null $wheres = null): array
    {
        $entityAvailable = [static::getName()];
        if (!empty($joins)) {
            $this->verifyJoins($joins, $entityAvailable);
        }
        $this->verifyFields($fields, $entityAvailable);
        $sql = $this->getQuerySelect($fields);
        if (!empty($joins)) {
            $sql .= $this->getQueryJoin($joins);
        }
        if (!empty($wheres)) {
            $sql .= $this->getQueryWhere($wheres);
        }
        $statement = (new LoginServer())->getPDO()->prepare($sql);
        if (!empty($wheres)) {
            $this->bindWhereValues($statement, $wheres);
        }
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}