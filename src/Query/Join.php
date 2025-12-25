<?php


namespace OrmLibrary\Query;

use Exception;
use OrmLibrary\Entity\EntityRepository;

class Join
{
    private string $entityFrom;
    private string $entityTo;

    private function hasEntityFrom(): bool
    {
        return !empty($this->entityFrom);
    }

    private function hasEntityTo(): bool
    {
        return !empty($this->entityTo);
    }

    //region getter and Setter
    public function getEntityFrom(): string
    {
        return $this->entityFrom;
    }

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

    public function getEntityTo(): string
    {
        return $this->entityTo;
    }

    public function setEntityTo(string $entity): void
    {
        $this->reset();
        if (EntityRepository::doEntityExist($entity))
            $this->entityTo = $entity;
        else
            throw new Exception("La table $entity n'existe pas.");

    }

    //endregion

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

    public function getQuery(): string
    {
        $entityTo = EntityRepository::getLink($this->getEntityTo(), $this->getEntityFrom());
        $entityFrom = EntityRepository::getLink($this->getEntityFrom(), $this->getEntityTo());

        return "INNER JOIN " . $this->getEntityTo()
            . " ON " . $this->getEntityFrom() . "." . $entityTo[array_key_first($entityTo)]
            . " = "
            . $this->getEntityTo() . "." . $entityFrom[array_key_first($entityFrom)];
    }

    public function reset(): void
    {
        unset($this->entityFrom);
        unset($this->entityTo);
    }

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
    public function field(string $entityTo):JoinBuilder {
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