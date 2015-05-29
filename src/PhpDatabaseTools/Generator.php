<?php

namespace PhpDatabaseTools;

use Exception;

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
    if (!$this->connect($config))
      throw new Exception("Connection failed");


    $dbStructure = array();
    $tableStructure = array();

    try
    {
      $tables = $this->tables();
      foreach ($tables as $table) {
        $status = $this->status($table);
        if (empty($status)) continue; //Posible view

        $tableStructure[$table] = array(
          "columns" => $this->tableColumns($table), // Get Columns
          "indexes" => $this->index($table), // Get Indexes
          "triggers" => $this->trigger($table), // Get Triggers
          "status" => $this->status($table) // Get Status
        );
      }
      $procedures = $this->procedures();
      $functions = $this->functions();
      $views = $this->views();
    }
    catch (Exception $e)
    {
      throw $e;
    }
    finally
    {
      $this->close();
    }

    $dbStructure["tables"] = $tableStructure;
    $dbStructure["procedures"] = $procedures;
    $dbStructure["functions"] = $functions;
    $dbStructure["views"] = $views;

    return $dbStructure;
  }

  private function status($table)
  {
    $status = array();
    if (!($result = $this->connection->getStatus($table))) { return $status; }
    while($row = $this->connection->row($result)) {
      $status = array(
        'Engine' => $row['ENGINE'],
        'Collation' => $row['COLLATION_NAME'],
        'Charset' => $row['CHARACTER_SET_NAME']
      );
    }
    return $status;
  }

  private function views(){
    $views = array();
    if (!($result = $this->connection->getViews())) { return $views; }
    while($row = $this->connection->row($result)) {
      $name = $row['TABLE_NAME'];
      unset($row['TABLE_NAME']);
      $views[$name] = $row;
    }
    return $views;
  }

  private function procedures(){
    $procedures = array();
    if (!($result = $this->connection->getProcedures())) { return $procedures; }
    while($row = $this->connection->row($result)) {
      $name = $row['name'];
      unset($row['name']);
      $procedures[$name] = $row;
    }
    return $procedures;
  }

  private function functions(){
    $functions = array();
    if (!($result = $this->connection->getFunctions())) { return $functions; }
    while($row = $this->connection->row($result)) {
      $name = $row['name'];
      unset($row['name']);
      $functions[$name] = $row;
    }
    return $functions;
  }

  private function tables(){
    $tables = array();
    if (!($result = $this->connection->getTables())) { return $tables; }
    while($row = $this->connection->row($result)) {
      $keys = array_keys($row);
      if (!isset($keys[0])) continue;
      $tables[] = $row[$keys[0]];
    }
    return $tables;
  }

  private function index($table){
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

  private function trigger($table){
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

  private function tableColumns($table){
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
