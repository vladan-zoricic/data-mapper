<?php

namespace G4\DataMapper\Mapper\Mysql;

use G4\DataMapper\Domain\DomainAbstract;
use G4\DataMapper\Mapper\MapperInterface;
use G4\DataMapper\Selection\Factory as SelectionFactory;
use G4\DataMapper\Selection\Identity;

abstract class MysqlAbstract implements MapperInterface
{
    protected static $_throwExceptionOnNewTrancationBegin = false;

    protected static $_transactionActive = false;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $_db;

    protected $_factoryDomainName = null;

    protected $_rawData = array();

    /**
     *
     * @var int
     */
    protected $_totalItemsCount;

    /**
     * @var SelectionFactory
     */
    protected $_selectionFactory = null;

    protected $_tableName = null;

    public function __construct(\G4\DataMapper\Adapter\Mysql\Db $adapter)
    {
        $this->_db = $adapter->get();
    }

    public function closeConnection()
    {
        $this->_db->closeConnection();
        return $this;
    }

    public function delete(Identity $identity)
    {
        $sql  = "DELETE FROM " . $this->_db->quoteIdentifier($this->_getTablaName(), true);
        $sql .= $this->_getSelectionFactory()->where($identity) ? (" WHERE " . $this->_getSelectionFactory()->where($identity)) : '';
        $sql .= $identity->hasOrderBy() ? (" ORDER BY " . join(',', $this->_getSelectionFactory()->orderBy($identity))) : '';
        $sql .= $identity->getLimit() ? (" LIMIT " . $identity->getLimit()) : '';

        $stmt = $this->_db->query($sql);
        return $stmt->rowCount();
    }

    public function query($sql)
    {
        return $this->_db->query($sql);
    }

    public function transactionBegin()
    {
        if(self::$_throwExceptionOnNewTrancationBegin === true && self::$_transactionActive === true) {
            throw new \Exception('Database transaction is already started');
        }

        self::$_transactionActive = true;
        return $this->_db->beginTransaction();
    }

    public function transactionRollback()
    {
        // reset transaction status flag
        self::$_transactionActive = false;
        return $this->_db->rollBack();
    }

    public function transactionCommit()
    {
        // reset transaction status flag
        self::$_transactionActive = false;
        return $this->_db->commit();
    }

    /**
     * @param Identity $identity
     *
     * @return \G4\DataMapper\Collection\CollectionAbstract
     */
    public function findAll(Identity $identity = null)
    {
        if ($identity === null) {
            $identity = $this->getIdentity();
        }

        return $this
            ->_fetchAll($identity)
            ->_fetchCount($identity)
            ->_returnCollection();
    }

    /**
     * @param Identity $identity
     *
     * @return DomainAbstract
     */
    public function findOne(Identity $identity)
    {
        return $this
            ->_fetchRow($identity)
            ->_returnDomain();
    }

    /**
     * @return Identity
     */
    public function getIdentity()
    {
        return new Identity();
    }

    public function hasRawData()
    {
        return !empty($this->_rawData);
    }

    public function insert(DomainAbstract $domain)
    {
        $this->setRawDataFromDomain($domain);

        $this->_db->insert($this->_getTablaName(), $this->_rawData);

        $lastId = $this->_db->lastInsertId();
        $domain->setId($lastId);

        return isset($lastId) ? $lastId : false;
    }

    public function insertBulk($collection)
    {
        if (!$collection instanceof  \Iterator && !is_array($collection)) {
            throw new \Exception('Collection in insertBulk() must implement Iterator or be an array.', 500);
        }

        if (empty($collection)) {
            throw new \Exception('Collection in insertBulk() must not be empty.', 500);
        }

        //@TODO setting fields may be solved better?
        $domain = is_array($collection)
            ? reset($collection)
            : $collection->rewind()->current();
        $fields = "`" . implode("`,`", array_keys($domain->getRawData())) . "`";
        $values = array();

        foreach ($collection as $domain) {
            $quotedValues = array();
            foreach ($domain->getRawData() as $value){
                $quotedValues[] = $this->_db->quote($value);
            }
            $values[] = "(" .implode(",", $quotedValues) . ")";
        }

        $query    = "INSERT INTO {$this->_getTablaName()} ({$fields}) VALUES " . implode(',', $values);
        $queryId  = $this->_db->getProfiler()->queryStart($query, \G4\DataMapper\Db\Db::getProfilerConstInsert());
        $response = $this->_db->getConnection()->exec($query);
        $this->_db->getProfiler()->queryEnd($queryId);

        return $response;
    }

    public function insertOnDuplicateKeyUpdate(DomainAbstract $domain)
    {
        $this->setRawDataFromDomain($domain);

        $table = $this->_getTablaName();

        $fields = implode(", ", array_keys($this->_rawData));
        $values = implode(", ", array_fill(1, count($this->_rawData), "?"));
        $update = implode(" = ?, ", array_keys($this->_rawData)) . " = ?";

        $query = "INSERT INTO {$table} ({$fields}) VALUES ({$values}) ON DUPLICATE KEY UPDATE {$update}";

        $stmt = $this->_db->query($query, array_merge(array_values($this->_rawData), array_values($this->_rawData)));

        return $stmt->rowCount();
    }

    public function setRawData(array $rawData)
    {
        $this->_rawData = $rawData;
        if (!$this->hasRawData()) {
            throw new \Exception('Raw data is empty');
        }
        return $this;
    }

    /**
     * @param DomainAbstract $domain
     * @throws \Exception
     * @return \G4\DataMapper\Mapper\Mysql\MysqlAbstract
     */
    public function setRawDataFromDomain(DomainAbstract $domain)
    {
        $this->_rawData = $domain->getRawData();
        if (!$this->hasRawData()) {
            throw new \Exception('Domain has no data');
        }
        return $this;
    }

    public function update(DomainAbstract $domain)
    {
        return $this
            ->setRawDataFromDomain($domain)
            ->_update('`' . $domain->getIdKey() . '` = ' . $this->_db->quote($domain->getId())); //TODO: Drasko: move this to selection factory!!!
    }

    public function updateAll(Identity $identity, array $rawData)
    {
        foreach($rawData as $key => $value) {
            if(empty($key) || is_numeric($key)) {
                throw new \Exception('Raw data values are not valid');
            }
            $fields[] = "{$key} = ?";
        }

        $sf = $this->_getSelectionFactory();
        $where = $sf->where($identity);

        if ($where == 1) {
            throw new \Exception('Selection identity can not be 1');
        }

        $table = $this->_getTablaName();
        $fields = implode(', ', $fields);

        $sql = "UPDATE {$table} SET {$fields} WHERE {$where}";

        if($identity->getLimit()) {
            $sql .= " LIMIT " . $sf->limit($identity);
        }

        return $this->_db->query($sql, array_values($rawData));
    }

    /**
     * @param Identity $identity
     *
     * @return MysqlAbstract
     */
    protected function _fetchAll(Identity $identity)
    {
        $this->_rawData = $this->_db->fetchAll($this->_getSelectWithLimit($identity));
        return $this;
    }

    /**
     * @param Identity $identity
     *
     * @return MysqlAbstract
     */
    protected function _fetchCount(Identity $identity)
    {
        $this->_totalItemsCount = $this->_db->fetchOne($this->_getSelectCount($identity));
        return $this;
    }

    /**
     * @param Identity $identity
     *
     * @return MysqlAbstract
     */
    protected function _fetchRow(Identity $identity)
    {
        $this->_rawData = $this->_db->fetchRow($this->_getSelect($identity));
        return $this;
    }

    /**
     * @return string
     */
    protected function _getFactoryDomainName()
    {
        return $this->_factoryDomainName;
    }

    /**
     * @param Identity $identity
     *
     * @return \Zend_Db_Select
     */
    protected function _getSelect(Identity $identity)
    {
        return $this->_db
            ->select()
            ->from($this->_getTablaName(), '*')
            ->where($this->_getSelectionFactory()->where($identity))
            ->order($this->_getSelectionFactory()->orderBy($identity));
    }

    /**
     * @param Identity $identity
     *
     * @return \Zend_Db_Select
     */
    protected function _getSelectCount(Identity $identity)
    {
        return $this->_db
            ->select()
            ->from($this->_getTablaName(), 'COUNT(*) AS cnt')
            ->where($this->_getSelectionFactory()->where($identity))
            ->order($this->_getSelectionFactory()->orderBy($identity));
    }

    /**
     * @param Identity $identity
     *
     * @return \Zend_Db_Select
     */
    protected function _getSelectWithLimit(Identity $identity)
    {
        $sf = $this->_getSelectionFactory();

        return $this->_getSelect($identity)->limit($sf->limit($identity), $sf->offset($identity));
    }

    /**
     * @return SelectionFactory
     */
    protected function _getSelectionFactory()
    {
        if ($this->_selectionFactory === null) {
            $this->_selectionFactory = new SelectionFactory($this->_db);
        }

        return $this->_selectionFactory;
    }

    /**
     * @return string
     */
    protected function _getTablaName()
    {
        return $this->_tableName;
    }

    /**
     * @return \G4\DataMapper\Collection\Content
     */
    protected function _returnCollection()
    {
        return new \G4\DataMapper\Collection\Content($this->_rawData, $this->_getFactoryDomainName(), $this->_totalItemsCount);
    }

    /**
     * @return \G4\DataMapper\Domain\DomainAbstract
     */
    protected function _returnDomain()
    {
        $factoryDomainName = $this->_getFactoryDomainName();
        $factoryDomain     = new $factoryDomainName();

        return $factoryDomain->createObject($this->_rawData);
    }

    private function _update($where)
    {
        return $this->_db->update($this->_getTablaName(), $this->_rawData, $where);
    }
}
