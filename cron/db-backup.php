<?php
/**
 * cron/db-backup.php — key-gated full-database SQL dump (plaintext, over HTTPS).
 *
 * The database is localhost-only on the server, so backups are generated here
 * and pulled off-site by an external job that ENCRYPTS them client-side before
 * storing. This endpoint never holds the encryption key — so a server breach
 * cannot decrypt stored backups.
 *
 *   curl -s "https://www.wiyulemotors.co.mw/cron/db-backup.php?key=WM_BACKUP_KEY" -o dump.sql
 *
 * Gated by WM_BACKUP_KEY (falls back to WM_CRON_KEY) from secrets.php.
 */
require __DIR__ . '/../includes/secrets.php';

$valid = defined('WM_BACKUP_KEY') ? WM_BACKUP_KEY
       : (defined('WM_CRON_KEY') ? WM_CRON_KEY : '');
if ($valid === '' || !hash_equals((string)$valid, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}

require __DIR__ . '/../includes/db_connection.php';

@set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');
header('X-Backup-Generated: ' . date('c'));

try {
    $conn->set_charset('utf8mb4');

    echo "-- Wiyule Motors database backup\n";
    echo "-- Generated: " . date('c') . "\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = [];
    $r = $conn->query("SHOW TABLES");
    while ($row = $r->fetch_row()) $tables[] = $row[0];

    $grand = 0;
    foreach ($tables as $t) {
        $tq = '`' . str_replace('`', '``', $t) . '`';

        echo "-- ----- Table: $t -----\n";
        echo "DROP TABLE IF EXISTS $tq;\n";
        $cr = $conn->query("SHOW CREATE TABLE $tq")->fetch_row();
        echo $cr[1] . ";\n\n";

        $res  = $conn->query("SELECT * FROM $tq");
        $cols = [];
        foreach ($res->fetch_fields() as $f) {
            $cols[] = '`' . str_replace('`', '``', $f->name) . '`';
        }
        $collist = implode(',', $cols);

        $n = 0;
        while ($row = $res->fetch_row()) {
            $vals = [];
            foreach ($row as $v) {
                $vals[] = ($v === null) ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
            }
            echo "INSERT INTO $tq ($collist) VALUES (" . implode(',', $vals) . ");\n";
            $n++;
        }
        $grand += $n;
        echo "-- $n row(s)\n\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    echo "-- Backup complete: " . count($tables) . " tables, $grand rows.\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo "\n-- BACKUP FAILED: " . $e->getMessage() . "\n";
}
