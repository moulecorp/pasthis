<?php

if (!file_exists ('pasthis.db'))
    exit;

$db = new SQLite3 ('pasthis.db', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
$res = $db->querySingle(
    "SELECT sql
     FROM sqlite_master;",
     true
);

$updated = false;

if (strpos($res['sql'], 'highlighting INTEGER') === false) {
    $db->exec("ALTER TABLE pastes ADD COLUMN highlighting INTEGER DEFAULT 1;");
    $updated = true;
}

print $updated ? "Updated." : "Up-to-date.";

?>
