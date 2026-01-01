<?php


namespace OrmLibrary\Query;

use Exception;
use OrmLibrary\Entity\EntityRepository;

class Join
{
    private string $entityFrom;
    private string $entityTo;

    /**
     * Checks whether the source entity has been defined.
     *
     * @return bool True if the source entity is set, false otherwise.
     */
    private function hasEntityFrom(): bool
    {
        return !empty($this->entityFrom);
    }

    /**
     * Checks whether the target entity has been defined.
     *
     * @return bool True if the target entity is set, false otherwise.
     */
    private function hasEntityTo(): bool
    {
        return !empty($this->entityTo);
    }

    //region getter and Setter

    /**
     * Returns the source entity of the JOIN.
     *
     * @return string The source entity name.
     */
    public function getEntityFrom(): string
    {
        return $this->entityFrom;
    }

    /**
     * Defines the source entity of the JOIN.
     *
     * The target entity have to be set first.
     * The source entity must be linked to the target entity.
     *
     * @param string $entity The source entity name.
     *
     * @return void
     *
     * @throws Exception If the target entity is not defined.
     * @throws Exception If the source entity does not exist.
     * @throws Exception If the entities are not linked.
     */
    public function setEntityFrom(string $entity): void
    {
        if (!$this->hasEntityTo())
            throw new Exception("La table à rejoindre n'a pas été renseigné.");

        if (EntityRepository::doEntityExist($entity)) {
            if (EntityRepository::isLinked($entity, $this->getEntityTo())) {
                $this->entityFrom = $entity;
            } else
                throw new Exception("La table " . $entity . " n'est pas liée à la table " . $this->getEntityTo() . ".");
        } else
            throw new Exception("La table $entity n'existe pas.");
    }

    /**
     * Returns the target entity of the JOIN.
     *
     * @return string The target entity name.
     */
    public function getEntityTo(): string
    {
        return $this->entityTo;
    }

    /**
     * Defines the target entity of the JOIN.
     *
     * Resets the current JOIN state before assigning the target entity.
     *
     * @param string $entity The target entity name.
     *
     * @return void
     *
     * @throws Exception If the entity does not exist.
     */
    public function setEntityTo(string $entity): void
    {
        $this->reset();
        if (EntityRepository::doEntityExist($entity))
            $this->entityTo = $entity;
        else
            throw new Exception("La table $entity n'existe pas.");

    }

    //endregion

    /**
     * Validates and resolves the JOIN against the available entities.
     *
     * If the source entity is not defined, it is automatically resolved
     * from the available entities based on existing relationships.
     *
     * The resolved target entity is added to the available entity list.
     *
     * @param array $entityAvailable List of entities currently available in the query.
     *
     * @return void
     *
     * @throws Exception If the target entity is not defined.
     * @throws Exception If no valid source entity can be resolved.
     * @throws Exception If the source entity is not available in the current context.
     */
    public function verify(array &$entityAvailable):void {
        if (!$this->hasEntityTo())
            throw new Exception("La table à rejoindre n'a pas été renseigné.");

        if ($this->hasEntityFrom()) {
            $notValid = true;
            foreach ($entityAvailable as $entity) {
                if ($entity == $this->getEntityFrom()) {
                    $notValid = false;
                    break;
                }
            }

            if ($notValid)
                throw new Exception("La table de départ n'est pas disponible.");
        }

        if (!$this->hasEntityFrom()) {
            foreach ($entityAvailable as $entity) {
                if (EntityRepository::isLinked($this->getEntityTo(), $entity)) {
                    $this->setEntityFrom($entity);
                    break;
                }
            }
        }

        if (!$this->hasEntityFrom())
            throw new Exception("La table " . $this->getEntityTo() . " est injoignable.");

        $entityAvailable[] = $this->getEntityTo();
    }

    /**
     * Generates the SQL INNER JOIN clause.
     *
     * The JOIN condition is built automatically using metadata
     * resolved from the ORM database cache.
     *
     * @return string The SQL INNER JOIN fragment.
     *
     * @throws Exception If the entity relationship cannot be resolved.
     */
    public function getQuery(): string
    {
        $entityTo = EntityRepository::getLink($this->getEntityTo(), $this->getEntityFrom());
        $entityFrom = EntityRepository::getLink($this->getEntityFrom(), $this->getEntityTo());

        return "INNER JOIN " . $this->getEntityTo()
            . " ON " . $this->getEntityFrom() . "." . $entityTo[array_key_first($entityTo)]
            . " = "
            . $this->getEntityTo() . "." . $entityFrom[array_key_first($entityFrom)];
    }

    /**
     * Resets the JOIN configuration.
     *
     * Clears both source and target entities.
     *
     * @return void
     */
    public function reset(): void
    {
        unset($this->entityFrom);
        unset($this->entityTo);
    }

    /**
     * Creates a new JoinBuilder instance.
     *
     * Provides a fluent API to construct JOIN clauses.
     *
     * @return JoinBuilder A new builder instance.
     */
    public static function builder():JoinBuilder {
        return new JoinBuilder();
    }

}

class JoinBuilder {
    private string $entityFrom;
    private string $entityTo;

    public function entityFrom(string $entityFrom):JoinBuilder {
        $this->entityFrom = $entityFrom;
        return $this;
    }
    public function entityTo(string $entityTo):JoinBuilder {
        $this->entityTo = $entityTo;
        return $this;
    }

    public function build():Join {
        $j = new Join();
        $j->setEntityFrom($this->entityFrom);
        $j->setEntityTo($this->entityTo);
        return $j;
    }
}