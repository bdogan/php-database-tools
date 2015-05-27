<?php

namespace PhpDatabaseTools;

class Generator {

  private $connection;

  private function connect($config) {
    if ($this->connection) $this->close();

    $this->connection = new Mysql();
    if (!$this->connection->connect($config)) {
      throw new Exception("Error Connecting Database " . $config["database"] . "@" . $config["server"]);
    }
  }

  private function close(){
    if (!$this->connection) return;
    $this->connection->close();
    $this->connection = null;
  }

  function __destruct(){
    if ($this->connection) {
      $this->connection->close();
    }
  }

  public function Generate($config){
    $this->connect($config);

    $tableStructure = array();

    try
    {
      $tables = $this->tables();
      foreach ($tables as $table) {
        $tableStructure[$table] = array(
          "columns" => $this->tableColumns($table), // Get Columns
          "indexes" => $this->index($table), // Get Indexes
          "triggers" => $this->trigger($table) // Get Triggers
        );
      }
    }
    catch (Exception $e)
    {
      throw $e;
    }
    finally
    {
      $this->close();
    }

    return $tableStructure;
  }

  public function tables(){
    $tables = array();
    if (!($result = $this->connection->getTables())) { return $tables; }
    while($row = $this->connection->row($result)) {
      $keys = array_keys($row);
      if (!isset($keys[0])) continue;
      $tables[] = $row[$keys[0]];
    }
    return $tables;
  }

  public function index($table){
    $indexes = array();
    if (!($result = $this->connection->getIndexes($table))) { return $indexes; }
    while($row = $this->connection->row($result)) {
      unset($row['Table']);
      unset($row['Cardinality']);
      unset($row['Packed']);

      $keyName = $row['Key_name'];
      unset($row['Key_name']);

      $row['Column_name'] = array((intVal($row['Seq_in_index'], 10) - 1) => $row['Column_name']);
      unset($row['Seq_in_index']);

      if (isset($indexes[$keyName])) {
        $indexes[$keyName]['Column_name'] = array_merge($indexes[$keyName]['Column_name'], $row['Column_name']);
        ksort($indexes[$keyName]['Column_name']);
        continue;
      }

      $indexes[$keyName] = $row;
    }
    return $indexes;
  }

  public function trigger($table){
    $triggers = array();
    if (!($result = $this->connection->getTriggers($table))) { return $triggers; }
    while($row = $this->connection->row($result)) {
      unset($row['Table']);
      unset($row['Definer']);
      unset($row['sql_mode']);
      unset($row['character_set_client']);
      unset($row['collation_connection']);
      unset($row['Database Collation']);

      $triggerName = $row['Trigger'];
      unset($row['Trigger']);

      $triggers[$triggerName] = $row;
    }
    return $triggers;
  }

  public function tableColumns($table){
    $columns = array();
    if (!($result = $this->connection->getColumns($table))) { return $columns; }
    while($row = $this->connection->row($result)) {
      $keys = array_keys($row);
      if (!isset($keys[0])) continue;
      $field = $row[$keys[0]];
      unset($row[$keys[0]]);
      $columns[$field] = $row;
    }
    return $columns;
  }

}
?>
