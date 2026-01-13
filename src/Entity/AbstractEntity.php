<?php

namespace OrmLibrary\Entity;

use Exception;
use OrmLibrary\Field\TypeField\IdField;
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

        $fields = [];
        $refClass = new ReflectionClass(static::class);

        foreach ($refClass->getProperties() as $property) {
            $attributes = $property->getAttributes();

            foreach ($attributes as $attribute) {
                $attribute = $attribute->newInstance();
                if (!($attribute instanceof AField))
                    continue; //Passe son chemin si pas un field

                $field = $attribute->getName();

                if ($attribute instanceof ARelationField)
                    $value = $property->getValue($this)->Id();
                else
                    $value = $property->getValue($this)->get();

                if (!$attribute->isNullable() && !isset($value))
                    throw new Exception("Field '" . $field . "' is not nullable.");
                $fields[$field] = $value;
            }
        }

        if (empty($fields))
            throw new Exception("No fields have been defined.");

        if(!$this->isNew()) {
            $w = Where::builder()->entity($this::getName())->field("Id")->value($this->id->get())->build();
            $this->repository->update($fields, $w);
        } else {
            unset($fields["Id"]);
            $this->isNew = false;
            $this->id->set($this->repository->insert($fields));
        }
    }

    /**
     * Reloads entity data from the database.
     *
     * @throws Exception If the entity has not been persisted yet
     */
    public function load():void {

        if ($this->isNew())
            throw new Exception("This entity has not been created yet.");

        $w = Where::builder()->entity($this::getName())->field("Id")->value($this->id->get())->build();
        $data = $this->repository->selectAll(wheres: $w)[0];

        $refClass = new ReflectionClass($this);

        foreach ($refClass->getProperties() as $property) {
            $attributes = $property->getAttributes();

            foreach ($attributes as $attribute) {
                $attribute = $attribute->newInstance();
                if (!($attribute instanceof AField))
                    continue; //Passe son chemin si pas un field

                $field =  $attribute->getName();
                if ($attribute->getName() == "Id")
                    continue; //Il est impossible d'overwrite l'id
                $property->getValue($this)->set($data[$field]);
            }
        }

    }

    public function delete():void {
        if ($this->isNew())
            throw new Exception("This entity has not been created yet.");

        $w = Where::builder()->entity($this::getName())->field("Id")->value($this->id->get())->build();
        $this->repository->delete($w);
    }
}