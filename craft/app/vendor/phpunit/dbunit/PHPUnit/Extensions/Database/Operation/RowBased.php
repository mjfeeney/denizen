<?php
/**
 * PHPUnit
 *
 * Copyright (c) 2002-2013, Sebastian Bergmann <sebastian@phpunit.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    DbUnit
 * @author     Mike Lively <m@digitalsandwich.com>
 * @copyright  2002-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://www.phpunit.de/
 * @since      File available since Release 1.0.0
 */

/**
 * Provides basic functionality for row based operations.
 *
 * To create a row based operation you must create two functions. The first
 * one, buildOperationQuery(), must return a query that will be used to create
 * a prepared statement. The second one, buildOperationArguments(), should
 * return an array containing arguments for each row.
 *
 * @package    DbUnit
 * @author     Mike Lively <m@digitalsandwich.com>
 * @copyright  2010-2013 Mike Lively <m@digitalsandwich.com>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @version    Release: @package_version@
 * @link       http://www.phpunit.de/
 * @since      Class available since Release 1.0.0
 */
abstract class PHPUnit_Extensions_Database_Operation_RowBased implements PHPUnit_Extensions_Database_Operation_IDatabaseOperation
{
    const ITERATOR_TYPE_FORWARD = 0;
    const ITERATOR_TYPE_REVERSE = 1;

    protected $operationName;

    protected $iteratorDirection = self::ITERATOR_TYPE_FORWARD;

    /**
     * @return string|boolean String containing the query or FALSE if a valid query cannot be constructed
     */
    protected abstract function buildOperationQuery(PHPUnit_Extensions_Database_DataSet_ITableMetaData $databaseTableMetaData, PHPUnit_Extensions_Database_DataSet_ITable $table, PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection);

    protected abstract function buildOperationArguments(PHPUnit_Extensions_Database_DataSet_ITableMetaData $databaseTableMetaData, PHPUnit_Extensions_Database_DataSet_ITable $table, $row);

    /**
    * Allows an operation to disable primary keys if necessary.
    *
    * @param PHPUnit_Extensions_Database_DataSet_ITableMetaData $databaseTableMetaData
    * @param PHPUnit_Extensions_Database_DataSet_ITable $table
    * @param PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection
    */
    protected function disablePrimaryKeys(PHPUnit_Extensions_Database_DataSet_ITableMetaData $databaseTableMetaData, PHPUnit_Extensions_Database_DataSet_ITable $table, PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection)
    {
        return FALSE;
    }

    /**
     * @param PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection
     * @param PHPUnit_Extensions_Database_DataSet_IDataSet $dataSet
     */
    public function execute(PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection, PHPUnit_Extensions_Database_DataSet_IDataSet $dataSet)
    {
        $databaseDataSet = $connection->createDataSet();

        $dsIterator = $this->iteratorDirection == self::ITERATOR_TYPE_REVERSE ? $dataSet->getReverseIterator() : $dataSet->getIterator();

        foreach ($dsIterator as $table) {
            $rowCount = $table->getRowCount();

            if($rowCount == 0) continue;

            /* @var $table PHPUnit_Extensions_Database_DataSet_ITable */
            $databaseTableMetaData = $databaseDataSet->getTableMetaData($table->getTableMetaData()->getTableName());
            $query                 = $this->buildOperationQuery($databaseTableMetaData, $table, $connection);
            $disablePrimaryKeys    = $this->disablePrimaryKeys($databaseTableMetaData, $table, $connection);

            if ($query === FALSE) {
                if ($table->getRowCount() > 0) {
                    throw new PHPUnit_Extensions_Database_Operation_Exception($this->operationName, '', array(), $table, "Rows requested for insert, but no columns provided!");
                }
                continue;
            }

            if ($disablePrimaryKeys) {
                $connection->disablePrimaryKeys($databaseTableMetaData->getTableName());
            }

            $statement = $connection->getConnection()->prepare($query);

            for ($i = 0; $i < $rowCount; $i++) {
                $args = $this->buildOperationArguments($databaseTableMetaData, $table, $i);

                try {
                    $statement->execute($args);
                }

                catch (Exception $e) {
                    throw new PHPUnit_Extensions_Database_Operation_Exception(
                      $this->operationName, $query, $args, $table, $e->getMessage()
                    );
                }
            }

            if ($disablePrimaryKeys) {
                $connection->enablePrimaryKeys($databaseTableMetaData->getTableName());
            }
        }
    }

    protected function buildPreparedColumnArray($columns, PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection)
    {
        $columnArray = array();

        foreach ($columns as $columnName) {
            $columnArray[] = "{$connection->quoteSchemaObject($columnName)} = ?";
        }

        return $columnArray;
    }
}
