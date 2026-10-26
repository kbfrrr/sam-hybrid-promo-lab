<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$tools = new Lab\LocalDatabaseTools((new Lab\Database())->connect());
echo json_encode($tools->inspect(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
