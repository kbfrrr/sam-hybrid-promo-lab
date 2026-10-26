<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$statements = Lab\LocalDatabaseTools::readSchema(__DIR__ . '/schema.sql');
$tools = new Lab\LocalDatabaseTools((new Lab\Database())->connect());
$tools->initialize($statements);
echo 'Table definitions executed. Verify all keys and constraints in your database tool.' . PHP_EOL;
