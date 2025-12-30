<?php

namespace OrmLibrary\Entity;

use Exception;
use OrmLibrary\DbContext;
use OrmLibrary\Query\Join;
use OrmLibrary\Query\Where;
use PDO;
use PDOStatement;

abstract class EntityRepository
{
    private static PDO $pdo;
    protected static string $entityName;
    private static ?array $dbData = null;

    public function __construct() {
        if (empty(static::$entityName))
            throw new \Exception("Some properties aren't defined yet.");
    }

    static public function getName(): string
    {
        if (!isset(static::$entityName))
            throw new Exception("Impossible to get an entity name from the abstract class");
        return static::$entityName;
    }

    private static function getPDO(): PDO
    {
        if (!isset(self::$pdo))
            self::$pdo = DbContext::getPdo();
        return self::$pdo;
    }

    static public function reloadData():void {
        $base = DbContext::getBase();
        $stmt = self::getPDO()->prepare(
                        "SELECT TABLE_NAME, COLUMN_NAME
                                FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = :database
                                ORDER BY TABLE_NAME, ORDINAL_POSITION;");
        $stmt->bindValue(':database', $base);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as $row)
            self::$dbData[$row["TABLE_NAME"]]["fields"][] = $row["COLUMN_NAME"];

        $stmt = self::getPDO()->prepare(
                "SELECT
                            TABLE_NAME AS table_source,
                            COLUMN_NAME AS colonne_source,
                            REFERENCED_TABLE_NAME AS table_cible,
                            REFERENCED_COLUMN_NAME AS colonne_cible
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = :database
                        AND REFERENCED_TABLE_NAME IS NOT NULL;"
        );
        $stmt->bindValue(':database', $base);
        $stmt->execute();
        $datas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($datas as $link) {
            self::$dbData[$link["table_source"]]["links"][$link["table_cible"]] = $link["colonne_source"];
            self::$dbData[$link["table_cible"]]["links"][$link["table_source"]] = $link["colonne_cible"];
        }
    }

    private static function getFields(string $entity):array {
        $entity = strtolower($entity);
        if (!self::doEntityExist($entity))
            throw new Exception("Entity $entity does not exist");
        $result = [];
        foreach (self::$dbData[$entity]["fields"] as $field) {
            $result[] = strtolower($field);
        }
        return $result;
    }


    private static function getLinks(string $entity):array {
        $entity = strtolower($entity);
        if (!self::doEntityExist($entity))
            throw new Exception("Entity $entity does not exist");
        return self::$dbData[$entity]["links"];
    }

    private static function getAssociations():array {
        if (is_null(self::$dbData))
            self::reloadData();
        $result = [];
        foreach (self::$dbData as $entity => $data) {
            if (count($data["links"]) == 2
                && count($data["fields"]) == 2
                && !in_array("id", $data["fields"])
                && !in_array("Id", $data["fields"])) {
                $result[$entity] = $data;
            }
        }
        return $result;
    }

    static public function doEntityExist(string $entity): bool
    {
        if (is_null(self::$dbData))
            self::reloadData();

        $tableList = [];
        foreach (self::$dbData as $table => $datas)
            $tableList[] = $table;

        if (in_array(strtolower($entity), $tableList))
            return true;
        return false;
    }

    static public function hasField(string $field, ?string $entity = null):bool {
        if (is_null(self::$dbData))
            self::reloadData();
        if (is_null($entity))
            $entity = self::getName();
        if (in_array(strtolower($field), self::getFields($entity))) {
            return true;
        }
        return false;
    }

    static public function isLinked(string $entity, ?string $entityOrigin = null): bool
    {
        if (is_null(self::$dbData))
            self::reloadData();
        if (is_null($entityOrigin))
            $entity = self::getName();

        $entityLinked = [];
        foreach (self::getLinks($entityOrigin) as $linkedEntity => $field) {
            $entityLinked[] = $linkedEntity;
        }
        if (in_array(strtolower($entity), $entityLinked)) {
            return true;
        }
        return false;
    }

    static public function getField(string $field, ?string $entity = null):string {
        if (is_null(self::$dbData))
            self::reloadData();
        if (is_null($entity))
            $entity = self::getName();
        if (!self::hasField($field, $entity))
            throw new Exception("The entity $entity does not have a field $field");

        $fields = self::getFields($entity);
        foreach ($fields as $fieldOriginal)
            if (strtolower($fieldOriginal) == strtolower($field))
                return $fieldOriginal;
        throw new Exception("The field $field was not found in the entity $entity");

    }

    static public function getLink(string $entity, ?string $entityOrigin = null):array {
        $entity = strtolower($entity);
        if (is_null(self::$dbData))
            self::reloadData();
        if (is_null($entityOrigin))
            $entity = self::getName();

        if (!self::isLinked($entity, $entityOrigin))
            throw new Exception("Entity $entityOrigin and entity $entity are not linked");

        $result = [];
        foreach (self::getLinks($entityOrigin) as $linkedEntity => $field) {
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

    static public function getAssociationEntity(string $entity, ?string $entityOrigin = null):array {
        $entity = strtolower($entity);
        if (is_null($entityOrigin))
            $entityOrigin = self::getName();

        $associations = self::getAssociations();
        $result = [];
        foreach ($associations as $entityName => $data) {
            if (self::isLinked($entityName, $entityOrigin) && self::isLinked($entityName, $entity))
                $result[$entityName] = $data;
        }
        if (empty($result))
            throw new Exception("$entityOrigin adn $entity are not linked by an association table");
        else
            return $result;
    }

    private function verifyFields(array $allFields, array $entityAvailable): void
    {
        foreach ($allFields as $entity => $fields) {
            if (!static::doEntityExist($entity))
                throw new Exception("Entity '$entity' does not exist");

            if (!in_array($entity, $entityAvailable))
                throw new Exception("Entity '$entity' is not available in the current context");

            foreach ($fields as $field) {
                if (!EntityRepository::hasField($field, $entity))
                    throw new Exception("Field '$field' does not exist in entity '$entity'");
            }
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

        $query = "INSERT INTO " . self::getName() . " (";

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

        $query = "UPDATE " . self::getName() . " SET ";

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
        return "DELETE FROM " . static::getName() . " ";
    }

    private function getQuerySelect(array $allFields): string
    {
        $query = "SELECT ";
        foreach ($allFields as $entity => $fields) {
            foreach ($fields as $key => $field) {
                $query .= $entity . "." . $field;

                if ($entity == array_key_last($allFields) && $key == array_key_last($fields))
                    $query = $query . " FROM " . self::getName();
                else
                    $query = $query . ", ";
            }
        }
        return $query;
    }

    private function getQueryWhere(array|Where $wheres): string
    {
        $query = " WHERE ";

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
        $pdo = self::getPDO();
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
        $statement = self::getPDO()->prepare($sql);
        $this->bindValues($statement, $values);
        $this->bindWhereValues($statement, $wheres);
        $statement->execute();
    }

    public function delete(Where|array $wheres): void
    {
        $sql = $this->getQueryDelete();
        $sql .= $this->getQueryWhere($wheres);
        $statement = self::getPDO()->prepare($sql);
        $this->bindWhereValues($statement, $wheres);
        $statement->execute();
    }

    public function select(array $fields, array|Join|null $joins = null, array|Where|null $wheres = null): array
    {
        $entityAvailable = [static::getName()];
        if (!empty($joins))
            $this->verifyJoins($joins, $entityAvailable);
        $this->verifyFields($fields, $entityAvailable);
        $sql = $this->getQuerySelect($fields);
        if (!empty($joins))
            $sql .= $this->getQueryJoin($joins);
        if (!empty($wheres))
            $sql .= $this->getQueryWhere($wheres);
        $statement = self::getPDO()->prepare($sql);
        if (!empty($wheres))
            $this->bindWhereValues($statement, $wheres);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectAll(array|Join|null $joins = null, array|Where|null $wheres = null): array
    {
        $entityAvailable = [static::getName()];
        $fields = [];
        if (!empty($joins))
            $this->verifyJoins($joins, $entityAvailable);
        foreach ($entityAvailable as $entity)
            $fields[$entity] = ["*"];
        $sql = $this->getQuerySelect($fields);
        if (!empty($joins))
            $sql .= $this->getQueryJoin($joins);
        if (!empty($wheres))
            $sql .= $this->getQueryWhere($wheres);
        $statement = self::getPDO()->prepare($sql);
        if (!empty($wheres))
            $this->bindWhereValues($statement, $wheres);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}