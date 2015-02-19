<?php

if (!file_exists (dirname(__FILE__) .'/pasthis.db'))
	exit;

$dsn = 'sqlite:' . dirname(__FILE__) .'/pasthis.db';
$db = new PDO ($dsn);

$query = $db->prepare (
    'SELECT sql
     FROM sqlite_master;'
);
$query->execute ();
$res = $query->fetch ();

$updated = false;

if (strpos($res['sql'], 'wrap INTEGER') === false) {
    $db->exec (
        'ALTER TABLE pastes
         ADD COLUMN wrap INTEGER DEFAULT 0;'
    );
    $updated = true;
}

print $updated ? "Updated." : "Up-to-date.";

?>
