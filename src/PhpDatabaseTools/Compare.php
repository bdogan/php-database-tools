<?php

namespace PhpDatabaseTools;

class Compare
{
  public static function generateHash($data)
  {
    $result = array();
    foreach ($data as $key => $value)
    {
      if (is_array($value))
        $result[$key] = crc32(serialize($value));
      else
        $result[$key] = crc32($value);
    }
    return $result;
  }

  public static function getDiff($refDbSchema, $targetDbSchema)
  {
    $Generator = new Generator();

    $refDbHash = self::generateHash($refDbSchema);
    $targetDbHash = self::generateHash($targetDbSchema);

    $result = array();
    foreach ($refDbHash as $table => $hash)
    {
      if (!isset($targetDbHash[$table]))
      {
        $result[$table] = "CREATE_NEW";
        continue;
      }
      if ($targetDbHash[$table] === $refDbHash[$table])
      {
        continue;
      }
      $result[$table] = "CHANGED";
    }

    unset($Generator);
    return $result;
  }

  public static function needUpgrade($refDbSchema, $targetDbSchema)
  {
    $results = self::getDiff($refDbSchema, $targetDbSchema);
    foreach ($results as $table => $result) {
      if ($results !== "ALL_SAME") return TRUE;
    }
    return FALSE;
  }

  public static function generateUpdateSql($refDbSchema, $targetDbSchema)
  {
      if (empty($tables = self::getDiff($refDbSchema, $targetDbSchema)))
      {
        return null;
      }
      $sqlStr = self::WriteSqlHeader();
      foreach ($tables as $table => $result) {
        if ($result == "CREATE_NEW")
          $sqlStr .= self::CreateStatement($table, $refDbSchema[$table]);
        else
          $sqlStr .= self::UpdateStatement($table, $refDbSchema[$table], $targetDbSchema[$table]);
      }
      return $sqlStr;
  }

  private static function WriteSqlHeader()
  {
    $hStr = "";
    $hStr .= "-- MySQL dump - bdogan/php-database-tools" . "\r\n";
    $hStr .= "-----------------------------------------" . "\r\n";
    $hStr .= "\r\n";
    $hStr .= "\r\n";
    $hStr .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;" . "\r\n";
    $hStr .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;" . "\r\n";
    $hStr .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;" . "\r\n";
    $hStr .= "/*!40101 SET NAMES utf8 */;" . "\r\n";
    $hStr .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;" . "\r\n";
    $hStr .= "/*!40103 SET TIME_ZONE='+00:00' */;" . "\r\n";
    $hStr .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;" . "\r\n";
    $hStr .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;" . "\r\n";
    $hStr .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" . "\r\n";
    $hStr .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;" . "\r\n";
    $hStr .= "\r\n";

    return $hStr;
  }

  private static function CreateStatement($table, $structure)
  {
    $cStr = "";

    $cStr .= "--" . "\r\n";
    $cStr .= sprintf("-- Table structure for table `%s`", $table) . "\r\n";
    $cStr .= "--" . "\r\n";
    $cStr .= "\r\n";
    $cStr .= sprintf("DROP TABLE IF EXISTS `%s`;", $table) . "\r\n";
    $cStr .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;" . "\r\n";
    $cStr .= "/*!40101 SET character_set_client = utf8 */;" . "\r\n";
    $cStr .= sprintf("CREATE TABLE `%s` (", $table) . "\r\n";

    $columns = array();
    foreach ($structure["columns"] as $column => $columnProp) {
      $columns[] = "\t" . self::CreateColumnStatement($column, $columnProp);

    }
    $cStr .= implode($columns, ",\r\n");
    if (count($structure["indexes"]) > 0) $cStr .= ",";
    $cStr .= "\r\n";

    $indexes = array();
    foreach ($structure["indexes"] as $index => $indexProp) {
      $indexes[] = "\t" . self::CreateIndexStatement($index, $indexProp);
    }
    $cStr .= implode($indexes, ",\r\n");
    if (count($indexes) === 2) $cStr .= " USING BTREE";
    $cStr .= "\r\n";
    $cStr .= sprintf(") ENGINE=%s DEFAULT CHARSET=%s;", "MyISAM", "latin5") . "\r\n";
    $cStr .= "/*!40101 SET character_set_client = @saved_cs_client */" . "\r\n";
    $cStr .= "\r\n";

    return $cStr;
  }

  private static function CreateIndexStatement($index, $indexProp)
  {
    $iStr = "";
    if ($index == "PRIMARY")
    {
      $iStr .= sprintf("PRIMARY KEY (`%s`)", implode($indexProp["Column_name"], "`,`"));
      return $iStr;
    }

    $keyType = "";
    if ($indexProp["Index_type"] == "BTREE") $keyType = "KEY";
    if ($indexProp["Index_type"] == "FULLTEXT") $keyType = "FULLTEXT KEY";
    if ($indexProp["Non_unique"] == "0") $keyType = "UNIQUE KEY";

    $iStr .= sprintf("%s `%s` (`%s`)",$keyType, $index, implode($indexProp["Column_name"], "`,`"));

    return $iStr;
  }

  private static function CreateColumnStatement($column, $columnProp)
  {
    $cStr = "";
    $cStr .= sprintf("`%s` %s", $column, $columnProp["Type"]);

    if ($columnProp["Null"] == "NO")
    {
      $cStr .= " NOT NULL";
    }
    else
    {
      $cStr .= " DEFAULT";
      if ($columnProp["Default"] == "") $columnProp["Default"] = "NULL";

      if ($columnProp["Default"] == "NULL" || $columnProp["Default"] == "CURRENT_TIMESTAMP")
        $cStr .= " " . $columnProp["Default"];
      else
        $cStr .= " '" . $columnProp["Default"] . "'";
    }

    if ($columnProp["Extra"] == "auto_increment")
    {
      $cStr .= " AUTO_INCREMENT";
    }

    return $cStr;
  }

  private static function UpdateStatement($table, $refStructure, $targetStructure)
  {
    return "Update $table" . "\r\n";
  }

}

?>
