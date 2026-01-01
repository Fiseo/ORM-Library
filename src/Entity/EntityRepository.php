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

    /***
     * Returns the name of the entity associated with the repository.
     *
     * The entity name must be defined in the concrete repository class.
     * Calling this method without a defined entity name will result in an exception.
     *
     * @return string The entity name.
     *
     * @throws Exception If the entity name is not defined.
     */
    static public function getName(): string
    {
        if (!isset(static::$entityName))
            throw new Exception("Impossible to get an entity name from the abstract class");
        return static::$entityName;
    }

    /***
     * Returns the PDO instance used by the repository.
     *
     * This method ensures that a single PDO instance is created and reused
     * across all repository calls. The connection is lazily initialized
     * using the DbContext when first requested.
     *
     * @return PDO The PDO connection instance.
     *
     * @throws Exception If the PDO instance cannot be created by the DbContext.
     */
    private static function getPDO(): PDO
    {
        if (!isset(self::$pdo))
            self::$pdo = DbContext::getPdo();
        return self::$pdo;
    }

    //region DBData

    /***
     * Initializes and caches database schema metadata.
     *
     * This method queries the database information schema to retrieve
     * table columns and foreign key relationships for the current database.
     * The collected metadata is stored in the static `$dbData` property
     * to avoid repeated queries during the application lifecycle.
     *
     * @return void
     *
     * @throws Exception If a database error occurs while querying the information schema.
     * @throws Exception If the database connection cannot be retrieved.
     */
    static public function initializeDBData():void {
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


    /***
     * Checks whether a given entity exists in the cached database metadata.
     *
     * Entity names are treated case-insensitively.
     *
     * @param string $entity The name of the entity to check.
     *
     * @return bool True if the entity exists in the database metadata, false otherwise.
     *
     * @throws Exception If the database metadata cannot be initialized.
     */
    static public function doEntityExist(string $entity): bool
    {
        if (is_null(self::$dbData))
            self::initializeDBData();

        $tableList = [];
        foreach (self::$dbData as $table => $datas)
            $tableList[] = $table;

        if (in_array(strtolower($entity), $tableList))
            return true;
        return false;
    }

    /***
     * Retrieves all field names (columns) of a specified entity in lowercase.
     *
     * @param string $entity The name of the entity to retrieve fields for.
     *
     * @return string[] An array of field names in lowercase, e.g. :
     *               ['id', 'name', 'email']
     *
     * @throws Exception If the specified entity does not exist in the database metadata.
     */
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


    /***
     * Retrieves all linked entities and their corresponding linking fields for a given entity.
     *
     * @param string $entity The name of the entity to retrieve links for.
     *
     * @return string[] An associative array where :
     *               - keys are linked entity names
     *               - values are the corresponding field names in the current entity
     *                 that establish the relationship.
     *
     *               Example:
     *               [
     *                   'user' => 'user_id',
     *                   'product' => 'product_id'
     *               ]
     *
     * @throws Exception If the specified entity does not exist in the database metadata.
     */
    private static function getLinks(string $entity):array {
        $entity = strtolower($entity);
        if (!self::doEntityExist($entity))
            throw new Exception("Entity $entity does not exist");
        return self::$dbData[$entity]["links"];
    }

    /***
     * Retrieves entities that act as association tables.
     *
     * Only tables with exactly two fields (excluding ID) and two links are considered.
     *
     * @return string[] An associative array where keys are association table names,
     *               and values are their metadata arrays, e.g.:
     *               [
     *                   'user_product' => [
     *                       'fields' => ['user_id', 'product_id'],
     *                       'links' => [
     *                           'user' => 'user_id',
     *                           'product' => 'product_id'
     *                       ]
     *                   ]
     *               ]
     *
     * @throws Exception If the database metadata cannot be initialized.
     */
    private static function getAssociations():array {
        if (is_null(self::$dbData))
            self::initializeDBData();
        $result = [];
        foreach (self::$dbData as $entity => $data) {
            if (count($data["links"]) == 2
                && count($data["fields"]) == 2
                && !in_array("Id", $data["fields"])) {
                $result[$entity] = $data;
            }
        }
        return $result;
    }

    /***
     * Checks if a field exists in the specified entity.
     *
     * If no entity is provided, it defaults to the repository's main entity.
     *
     * @param string $field The name of the field to check.
     * @param string|null $entity Optional entity name; defaults to the repository's entity.
     *
     * @return bool True if the field exists, false otherwise.
     *
     * @throws Exception If the database metadata cannot be initialized.
     * @throws Exception If used on the class EntityRepository.
     */
    static public function hasField(string $field, ?string $entity = null):bool {
        if (is_null(self::$dbData))
            self::initializeDBData();
        if (is_null($entity))
            $entity = self::getName();
        if (in_array(strtolower($field), self::getFields($entity))) {
            return true;
        }
        return false;
    }

    /***
     * Checks if a given entity is linked to another entity.
     *
     * If no origin entity is provided, it defaults to the repository's entity.
     *
     * @param string $entity The target entity to check for a link.
     * @param string|null $entityOrigin Optional origin entity; defaults to the repository's entity.
     *
     * @return bool True if a link exists, false otherwise.
     *
     * @throws Exception If the database metadata cannot be initialized.
     * @throws Exception If used on the class EntityRepository.
     */
    static public function isLinked(string $entity, ?string $entityOrigin = null): bool
    {
        if (is_null(self::$dbData))
            self::initializeDBData();
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

    /***
     * Returns the original casing of a field name in a given entity.
     *
     *
     * @param string $field The field name to retrieve.
     * @param string|null $entity Optional entity name; defaults to the repository's entity.
     *
     * @return string The field name with its original casing.
     *
     * @throws Exception If the field does not exist in the entity.
     * @throws Exception If used on the class EntityRepository.
     */
    static public function getField(string $field, ?string $entity = null):string {
        if (is_null(self::$dbData))
            self::initializeDBData();
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

    /***
     * Returns the linked field information between two entities.
     *
     * @param string $entity The target entity.
     * @param string|null $entityOrigin Optional origin entity; defaults to the repository's entity.
     *
     * @return string[] An associative array where:
     *               - key is the linked entity name
     *               - value is the field in the origin entity that links them
     *
     *               Example:
     *               [
     *                   'user' => 'user_id'
     *               ]
     *
     * @throws Exception If the database metadata cannot be initialized.
     * @throws Exception If the entities are not linked.
     * @throws Exception If no linked entity is found.
     * @throws Exception If default $entityOrigin is used on the class EntityRepository.
     */
    static public function getLink(string $entity, ?string $entityOrigin = null):array {
        $entity = strtolower($entity);
        if (is_null(self::$dbData))
            self::initializeDBData();
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

    /***
     * Returns association entities linking two entities via join tables.
     *
     * @param string $entity The target entity.
     * @param string|null $entityOrigin Optional origin entity; defaults to the repository's entity.
     *
     * @return string[] An associative array where:
     *               - keys are association table names
     *               - values are their metadata arrays with 'fields' and 'links'
     *
     *              Example:
     *               [
     *                   'user_product' => [
     *                       'fields' => ['user_id', 'product_id'],
     *                       'links' => [
     *                           'user' => 'user_id',
     *                           'product' => 'product_id'
     *                       ]
     *                   ]
     *               ]
     *
     * @throws Exception If the database metadata cannot be initialized.
     * @throws Exception If no association exists between the entities.
     * @throws Exception If default $entityOrigin is used on the class EntityRepository.
 */
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
    //endregion

    //region Verifiers
    /***
     * Verifies that provided fields exist in the specified entities
     * and that the entities are available in the current context.
     *
     * @param array $allFields Associative array of entity => fields, e.g.:
     *                         [
     *                             'user' => ['id', 'name'],
     *                             'product' => ['id', 'price']
     *                         ]
     * @param string[] $entityAvailable Array of entity names available in the current context.
     *
     * @return void
     *
     * @throws Exception If any entity does not exist.
     * @throws Exception If any entity is not available in the current context.
     * @throws Exception If any specified field does not exist in its entity.
     */
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

    /***
     * Verifies that provided values correspond to existing fields in the repository's entity.
     *
     * @param array $values Associative array of field => value.
     *
     * @return void
     *
     * @throws Exception If any field does not exist in the entity.
     */
    private function verifyValues(array $values): void
    {
        foreach ($values as $field => $value) {
            if (!static::hasField($field)) {
                throw new Exception("Field '$field' does not exist");
            }
        }
    }

    /***
     * Verifies that provided Join object(s) are valid and reference available entities.
     * Add the entity's name to the list of available entities when a join is valid.
     *
     * @param Join|Join[] $joins A single Join object or an array of Join objects.
     * @param array &$entityAvailable Reference to the array of entities available in the current context.
     *
     * @return void
     *
     * @throws Exception If a join references invalid or unavailable entities (handled inside Join::verify).
     */
    private function verifyJoins(array|Join $joins, array &$entityAvailable): void
    {
        if (is_array($joins)) {
            foreach ($joins as $join) {
                $join->verify($entityAvailable);
            }
        } else
            $joins->verify($entityAvailable);
    }
    //endregion

    //region Query Makers

    /***
     * Builds an SQL INSERT query for the current entity.
     * It's assumed that the values where verified before.
     *
     * @param string[] $values Associative array of field => value pairs to insert.
     *                      Keys must match existing entity fields.
     *
     * @return string The generated SQL INSERT query.
     *
     * @throws Exception If the entity name cannot be resolved.
     */
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

    /***
     * Builds an SQL UPDATE query for the current entity.
     * It's assumed that the values where verified before.
     *
     * The generated query does NOT include a WHERE clause.
     *
     * @param string[] $values Associative array of field => value pairs to update.
     *                      Keys must match existing entity fields.
     *
     * @return string The generated SQL UPDATE query without WHERE clause.
     *
     * @throws Exception If the entity name cannot be resolved.
     */
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

    /***
     * Builds an SQL DELETE query for the current entity.
     *
     * The generated query does NOT include a WHERE clause.
     *
     * @return string The generated SQL DELETE query.
     *
     * @throws Exception If the entity name cannot be resolved.
     */
    private function getQueryDelete(): string
    {
        return "DELETE FROM " . static::getName() . " ";
    }

    /***
     * Builds an SQL SELECT query with fully-qualified field names.
     * It's assumed that the fields where verified before.
     *
     * Expected $allFields format:
     * [
     *     'user' => ['id', 'name'],
     *     'post' => ['title']
     * ]
     *
     * The FROM clause always uses the repository's base entity.
     *
     * @param array $allFields Associative array of entity => list of fields.
     *
     * @return string The generated SQL SELECT query.
     *
     * @throws Exception If the entity name cannot be resolved.
     */
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

    /***
     * Builds an SQL WHERE clause from one or multiple Where objects.
     *
     * Multiple Where objects are combined using the AND operator.
     *
     * @param Where|Where[] $wheres A single Where instance or an array of Where instances.
     *
     * @return string The generated SQL WHERE clause.
     *
     * @throws Exception If a Where object wasn't fully set.
     */
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
    //endregion

    //region Binding

    /***
     * Builds SQL JOIN clauses from one or multiple Join objects.
     * It's assumed that the Join objects where verified before.
     *
     * @param Join|Join[] $joins A single Join instance or an array of Join instances.
     *
     * @return string The generated SQL JOIN clause(s).
     */
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

    /***
     * Binds entity field values to a prepared PDO statement.
     * It's assumed that the values where verified before.
     *
     * @param PDOStatement $statement The prepared PDO statement.
     * @param array        $values    Associative array of field => value pairs.
     *
     * @return void
     *
     * @throws Exception If a parameter name does not exist in the prepared statement.
     * @throws Exception If PDO fails to bind a value.
     */
    private function bindValues(PDOStatement $statement, array $values): void
    {
        foreach ($values as $field => $value) {
            $statement->bindValue(":" . $field, $value);
        }
    }

    /***
     * Binds values required by WHERE clause conditions.
     *
     * @param PDOStatement     $statement The prepared PDO statement.
     * @param Where|Where[]    $wheres    One or multiple Where conditions.
     *
     * @return void
     *
     * @throws Exception If a Where object wasn't fully set.
     */
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
    //endregion

    //region Queries

    /***
     * Inserts a new row into the entity table.
     *
     * @param array $values Associative array of field => value pairs to insert.
     *
     * @return int The ID of the newly inserted row. Return 0 when it's an association table.
     *
     * @throws Exception If at least one value is not valid.
     * @throws Exception If the PDO statement cannot be prepared or executed.
     */
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

    /***
     * Updates rows in the entity table matching the given WHERE conditions.
     *
     * At least one WHERE condition should be provided to avoid unintended updates.
     *
     * @param array            $values  Associative array of field => value pairs to update.
     * @param Where|Where[]    $wheres  One or more WHERE conditions.
     *
     * @return void
     *
     * @throws Exception If at least one value is not valid.
     * @throws Exception If a Where condition is not fully set.
     * @throws Exception If the PDO statement cannot be prepared or executed.
     */
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

    /***
     * Deletes rows from the entity table matching the given WHERE conditions.
     *
     * A WHERE condition is required to prevent full table deletion.
     *
     * @param Where|Where[] $wheres One or more WHERE conditions.
     *
     * @return void
     *
     * @throws Exception If a Where condition is not fully set.
     * @throws Exception If the PDO statement cannot be prepared or executed.
     */
    public function delete(Where|array $wheres): void
    {
        $sql = $this->getQueryDelete();
        $sql .= $this->getQueryWhere($wheres);
        $statement = self::getPDO()->prepare($sql);
        $this->bindWhereValues($statement, $wheres);
        $statement->execute();
    }

    /***
     * Executes a SELECT query with fields, optional joins and conditions.
     *
     * Fields must be declared per entity using the following format:
     * [
     *   'entity_name' => ['field1', 'field2']
     * ]
     *
     * @param array                $fields Fields to retrieve, grouped by entity.
     * @param Join|Join[]|null     $joins  Optional JOIN clauses.
     * @param Where|Where[]|null   $wheres Optional WHERE conditions.
     *
     * @return array A list of results as associative arrays.
     *
     * @throws Exception if at least one fields or entities is not valid.
     * @throws Exception If a JOIN is not valid.
     * @throws Exception If a Where condition is not fully set.
     * @throws Exception If the PDO statement cannot be prepared or executed.
     */
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

    /***
     * Retrieves all fields from the base entity and optional joined entities.
     *
     * This method automatically selects all columns for each available entity.
     *
     * @param Join|Join[]|null   $joins  Optional JOIN clauses.
     * @param Where|Where[]|null $wheres Optional WHERE conditions.
     *
     * @return array A list of results as associative arrays.
     *
     * @throws Exception If a JOIN is not valid.
     * @throws Exception If a Where condition is not fully set.
     * @throws Exception If the PDO statement cannot be prepared or executed.
 */
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
    //endregion
}