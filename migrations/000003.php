<?php
// This migration adds a "from_email" column to the notifications table.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('notifications') where name='from_email'");
$columnRequired = $columnQuery->fetch(PDO::FETCH_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE notifications ADD COLUMN from_email VARCHAR(255);');
}
