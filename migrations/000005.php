<?php
// This migration adds a "language" column to the user table and sets all values to english.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='language'");
$columnRequired = $columnQuery->fetch(PDO::FETCH_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN language TEXT DEFAULT "en"');
    $db->exec('UPDATE users SET language = "en"');
}
