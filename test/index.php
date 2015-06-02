<?php
  require_once __DIR__ . '/../vendor/autoload.php';
  require_once __DIR__ . '/config.php';

  if (isset($ignore) && is_array($ignore))
  {
    if (isset($ignore["table"]) && is_array($ignore["table"]))
    {
      PhpDatabaseTools\Compare::setIgnoreTable($ignore["table"]);
    }
  }

  $Generator = new PhpDatabaseTools\Generator();
?>
<html>
  <head>
    <meta charset="utf-8">
    <title>Php Database Tools</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body { padding-bottom: 50px; }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="row">
        <div class="col-xs-12">
          <h1>Overview <small>bdogan/php-database-tools</small></h1>
          <hr size=1 />
        </div>

          <div class="col-xs-6">
            <h3>DB1 Schema <small>Referance Database</small></h3>
            <pre class="pre-scrollable"><?php print_r($refDbSchema = $Generator->Generate($referanceDatabase)); ?></pre>
          </div>
          <div class="col-xs-6">
            <h3>DB2 Schema <small>Target Database</small></h3>
            <pre class="pre-scrollable"><?php print_r($targetDbSchema = $Generator->Generate($targetDatabase)); ?></pre>
          </div>

          <div class="col-xs-6">
            <h3>DB1 Hash <small>Referance Database</small></h3>
            <pre class="pre-scrollable"><?php print_r(PhpDatabaseTools\Compare::GenerateHash($refDbSchema)); ?></pre>
          </div>
          <div class="col-xs-6">
            <h3>DB2 Hash <small>Target Database</small></h3>
            <pre class="pre-scrollable"><?php print_r(PhpDatabaseTools\Compare::GenerateHash($targetDbSchema)); ?></pre>
          </div>

          <div class="col-xs-6">
            <h3>[DB1,DB2] Compare Result</h3>
            <pre class="pre-scrollable"><?php print_r(PhpDatabaseTools\Compare::getDiff($refDbSchema, $targetDbSchema)); ?></pre>
          </div>
          <div class="col-xs-6">
            <h3>IsSame?</h3>
            <pre class="pre-scrollable"><?php echo PhpDatabaseTools\Compare::isSame($refDbSchema, $targetDbSchema) ? "Yes" : "No"; ?></pre>
          </div>

          <div class="col-xs-12">
            <h3>DB1 => DB2 Update Sql</h3>
            <pre class="pre-scrollable"><?php echo PhpDatabaseTools\Compare::revisionSql($refDbSchema, $targetDbSchema) ?: "NULL"; ?></pre>
          </div>

        </div>
      </div>
    </div>
  </body>
</html>
