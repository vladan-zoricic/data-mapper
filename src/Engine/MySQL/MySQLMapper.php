<?php

namespace G4\DataMapper\Engine\MySQL;

use G4\DataMapper\Common\AdapterInterface;
use G4\DataMapper\Common\MapperInterface;
use G4\DataMapper\Common\MappingInterface;
use G4\DataMapper\Common\SelectionIdentityInterface;
use G4\Factory\ReconstituteInterface;

class MySQLMapper implements MapperInterface
{

    /**
     * @var MySQLAdapter
     */
    private $adapter;

    private $table;

    public function __construct(AdapterInterface $adapter, $table)
    {
        $this->adapter = $adapter;
        $this->type    = $table;
    }

    public function delete(MappingInterface $mappings)
    {
        $this->adapter->delete($this->table, $mappings->identifiers());
    }

    public function findAll(SelectionIdentityInterface $identity, ReconstituteInterface $factory)
    {
        $this->adapter->select($this->table, $this->makeSelectionFactory($selectionIdentity));
    }

    public function findOne(SelectionIdentityInterface $identity, ReconstituteInterface $factory)
    {

    }

    public function insert(MappingInterface $mappings)
    {
        $this->adapter->insert($this->table, $mappings->map());
    }

    public function update(MappingInterface $mappings)
    {
        $this->adapter->update($this->table, $mappings->map(), $mappings->identifiers());
    }

    private function makeSelectionFactory(SelectionIdentityInterface $selectionIdentity)
    {
        return new MySQLSelectionFactory($selectionIdentity);
    }
}