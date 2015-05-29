<?php

namespace PhpDatabaseTools;

class Compare
{

  public static function hashArray($data)
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

  public static function generateHash($data)
  {
    $result = array();
    foreach ($data as $element => $props)
    {
      $result[$element] = self::hashArray($props);
    }
    return $result;
  }

  public static function arrayDiff($arr1, $arr2, $posCheck = false)
  {
    $result = array();

    $arr1Hash = self::hashArray($arr1);
    $arr2Hash = self::hashArray($arr2);

    $arr1Keys = array_keys($arr1Hash);
    $arr2Keys = array_keys($arr2Hash);

    foreach ($arr1 as $key => $hash) {
      if (!isset($arr2[$key]))
      {
        $result[$key] = "CREATE_NEW";
        continue;
      }
      if ($posCheck === true && array_search($key, $arr1Keys) !== array_search($key, $arr2Keys))
      {
        $result[$key] = "POS_CHANGE_" . array_search($key, $arr1Keys) . "_" . array_search($key, $arr2Keys);
        continue;
      }
      if ($arr2[$key] === $arr1[$key])
      {
        continue;
      }
      $result[$key] = "CHANGED";
    }

    foreach ($arr2 as $key => $hash) {
      if (!isset($arr1[$key]))
      {
        $result[$key] = "DROP";
      }
    }

    return $result;
  }

  public static function getDiff($refDbSchema, $targetDbSchema)
  {
    $result = array();
    foreach ($refDbSchema as $element => $props)
    {
      $result[$element] = self::arrayDiff($refDbSchema[$element], $targetDbSchema[$element]);
    }
    return $result;
  }

  public static function isSame($refDbSchema, $targetDbSchema)
  {
    $results = self::getDiff($refDbSchema, $targetDbSchema);
    foreach ($results as $element => $props)
    {
      foreach ($props as $object => $result)
      {
        if ($result !== "ALL_SAME") return FALSE;
      }
    }
    return TRUE;
  }

  public static function revisionSql($refDbSchema, $targetDbSchema)
  {
      if (empty($differents = self::getDiff($refDbSchema, $targetDbSchema))) return null;

      $sqlStr = "";
      foreach ($differents as $element => $props)
      {
        if ($element == "tables") // Check if object is table
        {
          foreach ($props as $table => $result)
          {
            if ($result == "CREATE_NEW")
              $sqlStr .= self::CreateStatement($table, $refDbSchema["tables"][$table]);
            elseif ($result == "CHANGED")
              $sqlStr .= self::UpdateStatement($table, $refDbSchema["tables"][$table], $targetDbSchema["tables"][$table]);
            elseif ($result == "DROP")
              $sqlStr .= sprintf("DROP TABLE IF EXISTS `%s`;", $table) . "\r\n";
          }
        }
        if ($element == "functions" || $element == "procedures") //Check if object procedure or function
        {
          foreach ($props as $procedure => $result)
          {
            $type = (($element == "functions") ? "FUNCTION" : "PROCEDURE");
            if ($result == "CREATE_NEW")
              $sqlStr .= self::CreateProcedureStatement($type, $procedure, $refDbSchema[$element][$procedure]) . "\r\n";
            elseif ($result == "CHANGED")
            {
              $sqlStr .= sprintf("DROP %s IF EXISTS `%s`;", $type, $procedure) . "\r\n";
              $sqlStr .= self::CreateProcedureStatement($type, $procedure, $refDbSchema[$element][$procedure]) . "\r\n";
            }
            elseif ($result == "DROP")
              $sqlStr .= sprintf("DROP %s IF EXISTS `%s`;", $type, $procedure) . "\r\n";
          }
        }
        if ($element == "views") //Check if object view
        {
          foreach ($props as $view => $result)
          {
            if ($result == "CREATE_NEW")
              $sqlStr .= self::CreateViewStatement($view, $refDbSchema[$element][$view]) . "\r\n";
            elseif ($result == "CHANGED")
            {
              $sqlStr .= self::CreateViewStatement($view, $refDbSchema[$element][$view]) . "\r\n";
            }
            elseif ($result == "DROP")
              $sqlStr .= sprintf("DROP VIEW IF EXISTS `%s`;", $view) . "\r\n";
          }
        }
      }
      return $sqlStr;
  }

  private static function CreateViewStatement($name, $structure)
  {
    $vSql = "";
    $vSql .= sprintf("CREATE OR REPLACE VIEW `%s` AS %s;", $name, $structure['VIEW_DEFINITION']) . "\r\n";

    return $vSql;
  }

  private static function CreateProcedureStatement($type, $name, $structure)
  {
    $definer = explode("@", $structure['definer']);
    $definer = sprintf('`%s`@`%s`', $definer[0], $definer[1]);

    $returns = "";
    if ($type == "FUNCTION")
    {
      $returns = sprintf(" RETURNS %s", $structure['returns']);
    }

    $pSql = "";

    $pSql .= sprintf("DELIMITER %s", "$$") . "\r\n";
    $pSql .= sprintf("CREATE DEFINER=%s %s `%s`(%s)%s", $definer, $type, $name, $structure['param_list'], $returns) . "\r\n";
    $pSql .= sprintf("%s%s", $structure['body'], "$$") . "\r\n";
    $pSql .= sprintf("DELIMITER %s", ";") . "\r\n";

    return $pSql;
  }

  private static function CreateStatement($table, $structure)
  {
    $cStr = "";

    $cStr .= "\r\n";
    $cStr .= sprintf("DROP TABLE IF EXISTS `%s`;", $table) . "\r\n";
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
    $cStr .= implode($indexes, ",\r\n") . "\r\n";
    $cStr .= sprintf(") ENGINE=%s DEFAULT CHARSET=%s;", $structure["status"]["Engine"], $structure["status"]["Charset"]) . "\r\n";
    $cStr .= "\r\n";

    if (!empty($structure["triggers"]))
    {
      $cStr .= sprintf("DELIMITER %s", "$$") . "\r\n";
    }
    foreach ($structure["triggers"] as $trigger => $triggerProp)
    {
      $cStr .= sprintf("CREATE TRIGGER `%s` %s %s ON `%s` FOR EACH ROW %s%s", $trigger, $triggerProp["Timing"], $triggerProp["Event"], $table, $triggerProp["Statement"], "$$") . "\r\n";
    }
    if (!empty($structure["triggers"]))
    {
      $cStr .= sprintf("DELIMITER %s", ";") . "\r\n";
    }

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
    if ($indexProp["Index_type"] == "HASH") $keyType = "KEY";
    if ($indexProp["Index_type"] == "FULLTEXT") $keyType = "FULLTEXT KEY";
    if ($indexProp["Non_unique"] == "0") $keyType = "UNIQUE KEY";

    $iStr .= sprintf("%s `%s` (`%s`)",$keyType, $index, implode($indexProp["Column_name"], "`,`"));

    if ($indexProp["Index_type"] == "BTREE") $iStr .= " USING BTREE";
    if ($indexProp["Index_type"] == "HASH") $iStr .= " USING HASH";

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
    elseif ($columnProp["Null"] == "YES")
    {
      $cStr .= " NULL";
    }

    if ($columnProp["Default"] != "")
    {
      $cStr .= " DEFAULT";

      if ($columnProp["Default"] == "CURRENT_TIMESTAMP")
        $cStr .= " " . $columnProp["Default"];
      else
        $cStr .= " '" . $columnProp["Default"] . "'";
    }

    if ($columnProp["Extra"] != "")
    {
      $cStr .= " " . strtoupper($columnProp["Extra"]);
    }

    return $cStr;
  }



  private static function UpdateStatement($table, $refTable, $targetTable)
  {
    $uStr = "\r\n";

    $alterStatement = array();
    $columns = array_keys($refTable['columns']);

    // Checking Columns
    $columnDiff = self::arrayDiff($refTable["columns"], $targetTable["columns"]);
    foreach ($columnDiff as $column => $result) // Checking new created
    {
      $columnStatement = self::CreateColumnStatement($column, $refTable["columns"][$column]);
      $beforeColumn = isset($columns[array_search($column, $columns) - 1]) ? " AFTER `" . $columns[array_search($column, $columns) - 1] . "`" : "";

      if ($result == "CREATE_NEW")
      {
        $alterStatement[] = sprintf("\tADD COLUMN %s%s", $columnStatement, $beforeColumn);
      }
    }

    // Adding If Created New Column
    if (!empty($alterStatement))
    {
      $tmpTargetColumns = array();
      foreach ($refTable["columns"] as $column => $value)
      {
        if (!isset($targetTable["columns"][$column]))
        {
          $tmpTargetColumns[$column] = $refTable["columns"][$column];
        }
        else
        {
          $tmpTargetColumns[$column] = $targetTable["columns"][$column];
        }
      }
      $targetTable["columns"] = $tmpTargetColumns;
    }

    // Checking Updated Columns
    $columnDiff = self::arrayDiff($refTable["columns"], $targetTable["columns"], true);
    foreach ($columnDiff as $column => $result)
    {
      $columnStatement = self::CreateColumnStatement($column, $refTable["columns"][$column]);
      $beforeColumn = isset($columns[array_search($column, $columns) - 1]) ? " AFTER `" . $columns[array_search($column, $columns) - 1] . "`" : "";

      if ($result == "CHANGED" || strpos($result, "POS_CHANGE_") === 0)
        $alterStatement[] = sprintf("\tCHANGE COLUMN `%s` %s%s", $column, $columnStatement, $beforeColumn);
      elseif ($result == "DROP")
        $alterStatement[] = sprintf("\tDROP COLUMN `%s`", $column);
    }

    // Writing Changes
    if (!empty($alterStatement))
    {
      $uStr .= sprintf("ALTER TABLE `%s`\r\n%s;\r\n", $table, implode($alterStatement, ",\r\n"));
      $alterStatement = array();
    }

    // Checking Droping Indexes
    $indexDiff = self::arrayDiff($refTable["indexes"], $targetTable["indexes"], true);
    foreach ($indexDiff as $key => $result)
    {
      if ($result == "CHANGED" || strpos($result, "POS_CHANGE_") === 0)
        $alterStatement[] = sprintf("\tDROP INDEX `%s`", $key);
      elseif ($result == "DROP")
        $alterStatement[] = sprintf("\tDROP INDEX `%s`", $key);
    }

    // Writing Changes
    if (!empty($alterStatement))
    {
      $uStr .= sprintf("ALTER TABLE `%s`\r\n%s;\r\n", $table, implode($alterStatement, ",\r\n"));
      $alterStatement = array();
    }

    // Checking Created Indexes
    foreach ($indexDiff as $key => $result)
    {
      $keyType = "";
      $keyAlg = "";
      if ($result == "CREATE_NEW" || $result == "CHANGED" || strpos($result, "POS_CHANGE_") === 0)
      {
        if ($refTable["indexes"][$key]["Index_type"] == "BTREE") $keyType = "INDEX";
        if ($refTable["indexes"][$key]["Index_type"] == "HASH") $keyType = "INDEX";
        if ($refTable["indexes"][$key]["Index_type"] == "FULLTEXT") $keyType = "FULLTEXT";
        if ($refTable["indexes"][$key]["Non_unique"] == "0") $keyType = "UNIQUE";

        if ($refTable["indexes"][$key]["Index_type"] == "BTREE") $keyAlg .= " USING BTREE";
        if ($refTable["indexes"][$key]["Index_type"] == "HASH") $keyAlg .= " USING HASH";
      }

      if ($result == "CREATE_NEW")
        $alterStatement[] = sprintf("\tADD %s `%s`%s (`%s`)", $keyType, $key, $keyAlg, implode($refTable["indexes"][$key]["Column_name"], "`,`"));
      elseif ($result == "CHANGED" || strpos($result, "POS_CHANGE_") === 0)
      {
        $alterStatement[] = sprintf("\tADD %s `%s`%s (`%s`)", $keyType, $key, $keyAlg, implode($refTable["indexes"][$key]["Column_name"], "`,`"));
      }

    }

    if (!empty($alterStatement))
    {
      $uStr .= sprintf("ALTER TABLE `%s`\r\n%s;\r\n", $table, implode($alterStatement, ",\r\n"));
      $alterStatement = array();
    }

    // Checking Triggers
    $triggerDiff = self::arrayDiff($refTable["triggers"], $targetTable["triggers"], true);
    if (!empty($triggerDiff))
    {
      $uStr .= "\r\n";
      $uStr .= sprintf("DELIMITER %s", "$$") . "\r\n";
    }
    foreach ($triggerDiff as $trigger => $result) {
      if ($result == "CREATE_NEW")
      {
        $uStr .= sprintf("CREATE TRIGGER `%s` %s %s ON `%s` FOR EACH ROW %s%s", $trigger, $refTable["triggers"][$trigger]["Timing"], $refTable["triggers"][$trigger]["Event"], $table, $refTable["triggers"][$trigger]["Statement"], "$$") . "\r\n";
      }
      elseif ($result == "CHANGED")
      {
        $uStr .= sprintf("DROP TRIGGER `%s`%s", $trigger, "$$") . "\r\n";
        $uStr .= sprintf("CREATE TRIGGER `%s` %s %s ON `%s` FOR EACH ROW %s%s", $trigger, $refTable["triggers"][$trigger]["Timing"], $refTable["triggers"][$trigger]["Event"], $table, $refTable["triggers"][$trigger]["Statement"], "$$") . "\r\n";
      }
      elseif ($result == "DROP")
      {
        $uStr .= sprintf("DROP TRIGGER `%s`%s", $trigger, "$$");
      }
    }
    if (!empty($triggerDiff))
    {
      $uStr .= sprintf("DELIMITER %s", ";") . "\r\n";
    }

    return $uStr;
  }


}

?>
