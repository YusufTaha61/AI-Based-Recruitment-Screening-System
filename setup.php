<?php
require __DIR__ . '/includes/db.php';

$db->exec(file_get_contents(__DIR__ . '/includes/schema.sql'));
echo "Database tables are ready.\n";
