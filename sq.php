<?php

$hasFts5 = false;
try
{
    $db = new PDO('sqlite::memory:');
    $db->exec('CREATE VIRTUAL TABLE t USING fts5(x)');
    $hasFts5 = true;
}
catch (PDOException $e)
{
    echo "no such module: fts5\n";
}
?>