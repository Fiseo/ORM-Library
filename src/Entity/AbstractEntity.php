<?php

namespace OrmLibrary\Entity;

use OrmLibrary\Entity\EntityRepository;
use OrmLibrary\Field\TypeField\IdField;
use OrmLibrary\helpers;
use OrmLibrary\Query\Where;
use OrmLibrary\Field\AField;
use OrmLibrary\Relation\ARelationField;
use ReflectionClass;
use ReflectionProperty;

abstract class AbstractEntity
{
    protected static string $entityName;
    protected EntityRepository $repository;
    private bool $isNew;

    public function isNew() {
        return $this->isNew;
    }

    #[AField("Id", true)]
    readonly IdField $id;

    public function __construct($id = null)
    {
        if (empty(static::$entityName) || empty($this->repository))
            throw new \Exception("Some properties aren't defined yet.");

        $this->id = new IdField($this);

        if (!empty($id)) {
            $w = Where::builder()->entity($this::getName())->field("Id")->value($id)->build();
            if (!empty($this->repository->select(fields: [$this::getName() => ["Id"]], wheres: $w))) {
                $this->isNew = false;
                $this->id->set($id);
            }
            else
                throw new \Exception("An " . $this::getName() . " entity with this id doesn't exists.");
        } else
            $this->isNew = true;
    }

    static public function getName(): string{
        return static::$entityName;
    }

    public function getRepository(): EntityRepository {
        return $this->repository;
    }

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
                    throw new \Exception("Field '" . $field . "' is not nullable.");
                $fields[$field] = $value;
            }
        }

        if (empty($fields))
            throw new \Exception("No fields have been defined.");

        if(!$this->isNew()) {
            $w = Where::builder()->entity($this::getName())->field("Id")->value($this->id->get())->build();
            $this->repository->update($fields, $w);
        } else {
            unset($fields["Id"]);
            $this->isNew = false;
            $this->id->set($this->repository->insert($fields));
        }
    }
    public function load():void {

        if ($this->isNew())
            throw new \Exception("This entity has not been created yet.");

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

}