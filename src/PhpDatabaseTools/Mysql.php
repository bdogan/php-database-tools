<?php

namespace PhpDatabaseTools;

class Mysql {

    /**
     * @var resource Database resource
     */
    private $db;
    private $config;

    /**
     * Connect to the database.
     * @param str[] config
     */
    function connect($config) {
      $this->config = $config;
      if ($this->db = @mysql_pconnect($config['server'], $config['username'], $config['password'])) {
        if (isset($config['database']) && $this->select_db($config['database'])) return TRUE;
        return TRUE;
      }
      return FALSE;
    }

    /**
     * Close the database connection.
     */
    function close() {
        mysql_close($this->db);
    }

    /**
     * Use a database
     */
    function select_db($database) {
        if (mysql_select_db($database, $this->db)) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Get the columns in a table.
     * @param str table
     * @return resource A resultset resource
     */
    function getColumns($table) {
        return mysql_query(sprintf('SHOW COLUMNS FROM %s', $table), $this->db);
    }

    /**
     * Get a row from a table.
     * @param str table
     * @param str where
     * @return resource A resultset resource
     */
    function getRow($table, $where) {
        return mysql_query(sprintf('SELECT * FROM %s WHERE %s', $table, $where));
    }

    /**
     * Get the rows in a table.
     * @param str primary The names of the primary columns to return
     * @param str table
     * @return resource A resultset resource
     */
    function getTable($primary, $table) {
        return mysql_query(sprintf('SELECT %s FROM %s', $primary, $table));
    }

    /**
     * Get the tables in a database.
     * @return resource A resultset resource
     */
    function getTables() {
        return mysql_query('SHOW TABLES');
    }

    /**
     * Get the primary keys for the request table.
     * @return str[] The primary key field names
     */
    function getPrimaryKeys($table) {
        $resource = $this->getColumns($table);
        $primary = NULL;
        if ($resource) {
            while ($row = $this->row($resource)) {
                if ($row['Key'] == 'PRI') {
                    $primary[] = $row['Field'];
                }
            }
        }
        return $primary;
    }

    /**
     * Get the indexes for the request table.
     * @return resource A resultset resource
     */
    function getIndexes($table) {
        return mysql_query(sprintf('SHOW INDEX FROM %s', $table));
    }

    /**
     * Get the status for the request table.
     * @return resource A resultset resource
     */
    function getStatus($table) {
      return mysql_query(sprintf('SELECT * FROM information_schema.`TABLES` T,information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA WHERE CCSA.collation_name = T.table_collation AND T.table_schema = \'%s\' AND T.table_name = \'%s\'', $this->config["database"], $table));
    }

    /**
     * Get the triggers for the request table.
     * @return resource A resultset resource
     */
    function getTriggers($table) {
        return mysql_query(sprintf('SHOW TRIGGERS FROM %s LIKE \'%s\'', $this->config["database"], $table));
    }

    /**
     * Update a row.
     * @param str table
     * @param str values
     * @param str where
     * @return bool
     */
    function updateRow($table, $values, $where) {
        return mysql_query(sprintf('UPDATE %s SET %s WHERE %s', $table, $values, $where));
    }

    /**
     * Insert a new row.
     * @param str table
     * @param str names
     * @param str values
     * @return bool
     */
    function insertRow($table, $names, $values) {
        return mysql_query(sprintf('INSERT INTO %s (`%s`) VALUES ("%s")', $table, $names, $values));
    }

    /**
     * Get the columns in a table.
     * @param str table
     * @return resource A resultset resource
     */
    function deleteRow($table, $where) {
        return mysql_query(sprintf('DELETE FROM %s WHERE %s', $table, $where));
    }

    /**
     * Escape a string to be part of the database query.
     * @param str string The string to escape
     * @return str The escaped string
     */
    function escape($string) {
        return mysql_escape_string($string);
    }

    /**
     * Fetch a row from a query resultset.
     * @param resource resource A resultset resource
     * @return str[] An array of the fields and values from the next row in the resultset
     */
    function row($resource) {
        return mysql_fetch_assoc($resource);
    }

    /**
     * The number of rows in a resultset.
     * @param resource resource A resultset resource
     * @return int The number of rows
     */
    function numRows($resource) {
        return mysql_num_rows($resource);
    }

    /**
     * The number of rows affected by a query.
     * @return int The number of rows
     */
    function numAffected() {
        return mysql_affected_rows($this->db);
    }

    /**
     * Get the ID of the last inserted record.
     * @return int The last insert ID
     */
    function lastInsertId() {
        return mysql_insert_id();
    }

}

?>
