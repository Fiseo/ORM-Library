<?php

namespace OrmLibrary\Entity;

use Exception;
use OrmLibrary\Field\TypeField\IdField;
use OrmLibrary\Helpers;
use OrmLibrary\Query\Where;
use OrmLibrary\Field\AField;
use OrmLibrary\Relation\ARelationField;
use ReflectionClass;

abstract class AbstractEntity
{
    /**
     * Database table name associated with the entity.
     * Must be defined in child classes.
     */
    protected static string $entityName;

    /**
     * Indicates whether the entity has not yet been persisted.
     */
    protected EntityRepository $repository;

    /**
     * Returns whether the entity is new (not persisted yet).
     *
     * @return bool True if the entity is not stored in database
     */
    private bool $isNew;

    /**
     * Returns whether the entity is new (not persisted yet).
     *
     * @return bool True if the entity is not stored in database
     */
    public function isNew():bool {
        return $this->isNew;
    }

    /**
     * Unique identifier field of the entity.
     */
    #[AField("Id", true)]
    readonly IdField $id;

    /**
     * Entity constructor.
     * If an ID is provided, assigne the ID.
     *
     * @param int|null $id Entity identifier
     *
     * @throws Exception If entity configuration is incomplete
     * @throws Exception If no entity exists for the given ID
     */
    public function __construct(?int $id = null)
    {
        if (empty(static::$entityName) || empty($this->repository))
            throw new Exception("Some properties aren't defined yet.");

        $this->id = new IdField($this);

        if (!empty($id)) {
            $w = Where::builder()->entity($this::getName())->field("Id")->value($id)->build();
            if (!empty($this->repository->select(fields: [$this::getName() => ["Id"]], wheres: $w))) {
                $this->isNew = false;
                $this->id->set($id);
            }
            else
                throw new Exception("An " . $this::getName() . " entity with this id doesn't exists.");
        } else
            $this->isNew = true;
    }

    /**
     * Provides custom debug information for var_dump() and debug tools.
     *
     * Expose information about each field define in the class.
     * Also expose the name of the Entity and the value of
     * the property isNew.
     */
    public function __debugInfo(): ?array
    {
        $refClass = new ReflectionClass($this);
        foreach ($refClass->getProperties() as $property) {
            if (is_object($property->getValue($this)))
                $property->getValue($this)->__debugInfo();
        }
        return [
            'name' => $this::getName(),
            'isNew' => $this->isNew()
        ];
    }

    /**
     * Checks if the current entity class inherits directly from another entity class
     * other than the base AbstractEntity.
     *
     * @return bool False if it directly extends AbstractEntity. True otherwise.
     */
    private function isInheritor():bool {
        $refClass = new ReflectionClass(static::class);
        return ($refClass->getParentClass() != new ReflectionClass(AbstractEntity::class));
    }


    /**
     * Retrieves the entity fields and their values.
     *
     * Relation fields return their related entity identifier instead of the entity itself.
     *
     * When nullable fields are not allowed, an exception is thrown if a non-nullable
     * field has no value.
     *
     * @param bool $allowNullable Whether nullable fields are allowed to be empty.
     *
     * @return array{
     * fields: array<string, mixed>,
     * reflection: ReflectionClass }
     *
     * @throws Exception If a non-nullable field has no value and $allowNullable is false.
     */
    private function getFields(bool $allowNullable):array {
        $refClass = new ReflectionClass($this);
        $fields = [];
        foreach ($refClass->getProperties() as $property) {

            foreach ($property->getAttributes() as $attribute) {
                $attribute = $attribute->newInstance();
                if (!($attribute instanceof AField))
                    continue;

                $field = $attribute->getName();

                if ($attribute instanceof ARelationField)
                    $value = $property->getValue($this)->Id();
                else
                    $value = $property->getValue($this)->get(false);

                if (!$allowNullable && !$attribute->isNullable() && !isset($value))
                    throw new Exception("Field '" . $field . "' is not nullable.");
                $fields[$field] = $value;
            }
        }

        return [
            "fields" => $fields,
            "reflection" => $refClass
        ];
    }

    /**
     * Retrieves only the fields declared directly on the current entity class.
     *
     * Inherited fields from parent classes are ignored.
     *
     * Relation fields return the identifier of the related entity instead of the entity itself.
     *
     * When nullable fields are not allowed, an exception is thrown if a non-nullable
     * field has no value.
     *
     * @param bool $allowNullable Whether nullable fields are allowed to be empty.
     * @param AbstractEntity $clone A instance used to synchronize entity state.
     *
     * @return array{
     *fields: array<string, mixed>,
     *reflection: ReflectionClass }
     *
     * @throws Exception If a non-nullable field has no value and $allowNullable is false.
     */
    private function getOwnFields(bool $allowNullable, AbstractEntity $clone):array {
        $refClass = new ReflectionClass($this);
        $this->clone($clone);
        $fields = [];
        foreach ($refClass->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() != $refClass->getName())
                continue;

            foreach ($property->getAttributes() as $attribute) {
                $attribute = $attribute->newInstance();
                if (!($attribute instanceof AField))
                    continue;

                $field = $attribute->getName();

                if ($attribute instanceof ARelationField)
                    $value = $property->getValue($this)->Id();
                else
                    $value = $property->getValue($this)->get(false);

                if (!$allowNullable && !$attribute->isNullable() && !isset($value))
                    throw new Exception("Field '" . $field . "' is not nullable.");
                $fields[$field] = $value;
            }
        }

        return [
            "fields" => $fields,
            "reflection" => $refClass
        ];
    }

    /**
     * Returns the database table name of the entity.
     *
     * @return string
     */
    static public function getName(): string{
        return static::$entityName;
    }

    /**
     * Returns the repository associated with the entity.
     *
     * @return EntityRepository
     */
    public function getRepository(): EntityRepository {
        return $this->repository;
    }

    protected function setRepository(EntityRepository $repository):void {
        if(!isset($this->repository))
            $this->repository = $repository;
    }

    /**
     * Persists the entity to the database.
     * Performs INSERT if new, otherwise UPDATE.
     *
     * @throws Exception If a non-nullable field is null
     * @throws Exception If no fields are defined
     */
    public function save():void {
        $refClass = new ReflectionClass(static::class);

        if (!$this->isInheritor()) {
            $data = $this->getFields(false);
            $repository = $data["reflection"]->newInstance()->getRepository();
            $fields = $data["fields"];

            if (empty($fields))
                throw new Exception("No fields have been defined.");

            if (!$this->isNew()) {
                $w = Where::builder()->entity($this::getName())->field("Id")->value($this->id->get())->build();
                $repository->update($fields, $w);
            } else {
                $this->isNew = false;
                $this->id->set($repository->insert($fields));
            }
            return;
        }

        $parentRefClass = $refClass;
        $datas = [];
        $fieldCount = 0;

        //The data of each entity is retrieved
        while ($parentRefClass->getName() !== (new ReflectionClass(AbstractEntity::class))->getName()) {
            $parentClass = $parentRefClass->newInstance();
            $data = $parentClass->getOwnFields(false, $this);
            $fieldCount += count($data['fields']);
            $datas[] = $data;
            $parentRefClass = $parentRefClass->getParentClass();
        }

        if ($fieldCount === 0)
            throw new Exception("No fields have been defined.");

        $isNewInitial = $this->isNew();
        $firstDone = false;
        $i = 0;
        while (!$firstDone) {
            $data = $datas[$i];

            /** @var AbstractEntity $class */
            $class = $data["reflection"]->newInstance();

            //Search the first Entity
            if ($class->isInheritor()) {
                $i++;
                continue;
            }

            $fields = $data["fields"];
            $repository = $class->getRepository();


            //Save the data in the database
            if (!$this->isNew()) {
                $w = Where::builder()->entity($class::getName())->field("Id")->value($this->id->get())->build();
                $repository->update($fields, $w);
            } else {
                $this->isNew = false;
                $this->id->set($repository->insert($fields));
            }
            $firstDone = true;
        }

        unset($datas[$i]);//Delete to data of the Entity just added
        $this->isNew = $isNewInitial;//Allow is back at his initial value to know if an Insert or an Update is needed

        //Save the rest of the data
        foreach ($datas as $data) {
            $fields = $data["fields"];

            /** @var AbstractEntity $class */
            $class = $data["reflection"]->newInstance();

            $repository = $class->getRepository();

            if (!$this->isNew()) {
                $w = Where::builder()->entity($class::getName())->field("Id")->value($this->id->get())->build();
                $repository->update($fields, $w);
            } else {
                $fields["Id"] = $this->id->get();
                $repository->insert($fields);
            }
        }

        $this->isNew = false;
    }

    /**
     * Reloads entity data from the database.
     *
     * @throws Exception If the entity has not been persisted yet
     */
    public function load():void {

        if ($this->isNew())
            throw new Exception("This entity has not been created yet.");

        if(!$this->isInheritor()) {
            $w = Where::builder()->entity($this::getName())->field("Id")->value($this->id->get())->build();
            $data = $this->repository->selectAll(wheres: $w)[0];

            $this->import($data);
            return;
        }

        $parentRefClass = new ReflectionClass($this);
        $repos = [];

        //The repository of each parent is retrieved
        while ($parentRefClass->getName() !== (new ReflectionClass(AbstractEntity::class))->getName()) {
            $parentClass = $parentRefClass->newInstance();
            $repos[] = $parentClass->getOwnFields(false, $this)['reflection']->newInstance()->getRepository();
            $parentRefClass = $parentRefClass->getParentClass();
        }

        $datas = [];

        //The full set of data is queried.
        foreach ($repos as $repo) {
            /** @var EntityRepository $repo */
            $w = Where::builder()->entity($repo::getName())->field("Id")->value($this->id->get())->build();
            $datas = array_merge($datas, $repo->selectAll(wheres: $w)[0]);
        }

        $this->import($datas);
    }

    public function delete():void {
        if ($this->isNew())
            throw new Exception("This entity has not been created yet.");

        $w = Where::builder()->entity($this::getName())->field("Id")->value($this->id->get())->build();
        $this->repository->delete($w);
    }

    /**
     * Exports the entity data into an associative array.
     *
     * @return array<string, mixed> An array containing the exported field values.
     */
    public function export():array {
        $data = $this->getFields(true);
        return $data["fields"];

    }

    /**
     * Imports data into the entity.
     *
     * The entity identifier (Id) cannot be overwritten.
     * Fields not present in the input array or having a null value are ignored.
     *
     * @param array<string, mixed> $data The data to import into the entity.
     *
     * @return void
     */
    public function import(array $data):void
    {
        $refClass = new ReflectionClass($this);

        foreach ($refClass->getProperties() as $property) {
            $attributes = $property->getAttributes();

            foreach ($attributes as $attribute) {
                $attribute = $attribute->newInstance();
                if (!($attribute instanceof AField))
                    continue;

                $field =  $attribute->getName();
                if ($attribute->getName() == "Id")
                    continue;//Id cannot be overwrite
                if (array_key_exists($field, $data) && $data[$field] != null)
                    $property->getValue($this)->set($data[$field]);
            }
        }
    }

    /**
     * Clones the data from another entity of the same type into this entity.
     *
     * @param AbstractEntity $entity The entity to clone from. Must be of the same
     * class, a children of it or on of his parents.
     *
     * @throws Exception If the provided entity is not of the same type.
     */
    public function clone(AbstractEntity $entity):void {
        $refClass = new ReflectionClass($this);
        $parentRefClass = $refClass->getParentClass();

        //Retrieve the first Entity
        while($parentRefClass->getName() !== (new ReflectionClass(AbstractEntity::class))->getName()){
            $refClass = $parentRefClass;
            $parentRefClass = $refClass->getParentClass();
        }

        if (!Helpers::classValidator($entity, $refClass->getName()))
            throw new Exception("Wrong type of entity given to clone()");

        $this->import($entity->export());
    }
}