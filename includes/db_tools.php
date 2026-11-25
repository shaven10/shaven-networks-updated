<?php

function shaven_db_tools_can() {
    return shaven_role_is(1, 2);
}

function shaven_db_tools_root() {
    return SHAVEN_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'db-tools';
}

function shaven_db_tools_backup_dir() {
    return shaven_db_tools_root() . DIRECTORY_SEPARATOR . 'backups';
}

function shaven_db_tools_snapshot_dir() {
    return shaven_db_tools_root() . DIRECTORY_SEPARATOR . 'snapshots';
}

function shaven_db_tools_ensure_dirs() {
    foreach ([shaven_db_tools_root(), shaven_db_tools_backup_dir(), shaven_db_tools_snapshot_dir()] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }
    return true;
}

function shaven_db_tools_preserve_tables() {
    return [
        'tbl_subs_profile',
        'tbl_plans',
        'tbl_sys_users',
        'tbl_months',
        'tbl_mediums',
        'tbl_admin_level',
        'tbl_collector_ledger',
        'tbl_app_settings',
    ];
}

function shaven_db_tools_transaction_tables() {
    return [
        'tbl_remittances',
        'tbl_remit_batches',
        'tbl_payments',
        'tbl_finances',
        'tbl_plan_changes',
        'tbl_notifications',
    ];
}

function shaven_db_tools_list_tables($conn) {
    $tables = [];
    $result = mysqli_query($conn, 'SHOW TABLES');
    if (!$result) {
        return $tables;
    }
    while ($row = mysqli_fetch_row($result)) {
        $name = (string)($row[0] ?? '');
        if ($name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            $tables[] = $name;
        }
    }
    mysqli_free_result($result);
    return $tables;
}

function shaven_db_tools_sql_value($value) {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return "'" . str_replace(
        ["\\", "\0", "\n", "\r", "'", '"', "\x1a"],
        ["\\\\", '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
        (string)$value
    ) . "'";
}

function shaven_db_tools_dump_table($conn, $table, $includeStructure = true) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return '';
    }
    $sql = "-- Table: {$table}\n";
    if ($includeStructure) {
        $create = mysqli_query($conn, 'SHOW CREATE TABLE `' . $table . '`');
        if (!$create || !($row = mysqli_fetch_assoc($create))) {
            return $sql . "-- Could not read structure for {$table}\n";
        }
        mysqli_free_result($create);
        $createSql = (string)($row['Create Table'] ?? reset($row));
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createSql . ";\n\n";
    } else {
        $sql .= "DELETE FROM `{$table}`;\n";
    }
    $result = mysqli_query($conn, 'SELECT * FROM `' . $table . '`');
    if (!$result) {
        return $sql;
    }
    $fields = [];
    while ($field = mysqli_fetch_field($result)) {
        $fields[] = '`' . $field->name . '`';
    }
    if (!$fields) {
        mysqli_free_result($result);
        return $sql . "\n";
    }
    $batch = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $values = [];
        foreach ($row as $value) {
            $values[] = shaven_db_tools_sql_value($value);
        }
        $batch[] = '(' . implode(',', $values) . ')';
        if (count($batch) >= 50) {
            $sql .= 'INSERT INTO `' . $table . '` (' . implode(',', $fields) . ") VALUES\n" . implode(",\n", $batch) . ";\n";
            $batch = [];
        }
    }
    if ($batch) {
        $sql .= 'INSERT INTO `' . $table . '` (' . implode(',', $fields) . ") VALUES\n" . implode(",\n", $batch) . ";\n";
    }
    mysqli_free_result($result);
    return $sql . "\n";
}

function shaven_db_tools_write_dump($conn, array $tables, $path, array $meta, $includeStructure = true) {
    if (!shaven_db_tools_ensure_dirs()) {
        return ['ok' => false, 'error' => 'The backup folder could not be created.'];
    }
    $handle = @fopen($path, 'wb');
    if (!$handle) {
        return ['ok' => false, 'error' => 'The dump file could not be created.'];
    }
    $header = "-- SHAVEN Networks database tools dump\n";
    $header .= '-- Created: ' . date('c') . "\n";
    $header .= '-- Kind: ' . (string)($meta['kind'] ?? 'backup') . "\n";
    if (!empty($meta['label'])) {
        $header .= '-- Label: ' . str_replace(["\r", "\n"], ' ', (string)$meta['label']) . "\n";
    }
    $header .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
    fwrite($handle, $header);
    foreach ($tables as $table) {
        fwrite($handle, shaven_db_tools_dump_table($conn, $table, $includeStructure));
    }
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    $metaPath = $path . '.meta.json';
    $meta['file'] = basename($path);
    $meta['bytes'] = (int)@filesize($path);
    $meta['created_at'] = date('c');
    $meta['tables'] = array_values($tables);
    @file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return ['ok' => true, 'error' => '', 'path' => $path, 'meta' => $meta];
}

function shaven_db_tools_slug($label) {
    $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$label), '-'));
    $slug = substr($slug, 0, 40);
    return $slug !== '' ? $slug : 'point';
}

function shaven_db_tools_create_backup($conn, $userId, $label = '') {
    if (!shaven_db_tools_can()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can create a backup.'];
    }
    $tables = shaven_db_tools_list_tables($conn);
    if (!$tables) {
        return ['ok' => false, 'error' => 'No tables were found to back up.'];
    }
    $stamp = date('Ymd_His');
    $slug = shaven_db_tools_slug($label !== '' ? $label : 'full');
    $name = 'backup_' . $stamp . '_' . $slug . '.sql';
    $path = shaven_db_tools_backup_dir() . DIRECTORY_SEPARATOR . $name;
    $result = shaven_db_tools_write_dump($conn, $tables, $path, [
        'kind' => 'backup',
        'label' => trim((string)$label) !== '' ? trim((string)$label) : 'Full backup',
        'created_by' => (int)$userId,
        'scope' => 'full',
    ], true);
    if (!$result['ok']) {
        return $result;
    }
    return [
        'ok' => true,
        'error' => 'Backup saved as ' . $name . '.',
        'file' => $name,
        'path' => $path,
    ];
}

function shaven_db_tools_create_snapshot($conn, $userId, $label) {
    if (!shaven_db_tools_can()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can create a restore point.'];
    }
    $label = trim((string)$label);
    if ($label === '') {
        return ['ok' => false, 'error' => 'Enter a name for this restore point.'];
    }
    $tables = [];
    $existing = shaven_db_tools_list_tables($conn);
    foreach (shaven_db_tools_transaction_tables() as $table) {
        if (in_array($table, $existing, true)) {
            $tables[] = $table;
        }
    }
    if (!$tables) {
        return ['ok' => false, 'error' => 'No transaction tables were found for a restore point.'];
    }
    $stamp = date('Ymd_His');
    $name = 'snap_' . $stamp . '_' . shaven_db_tools_slug($label) . '.sql';
    $path = shaven_db_tools_snapshot_dir() . DIRECTORY_SEPARATOR . $name;
    $result = shaven_db_tools_write_dump($conn, $tables, $path, [
        'kind' => 'snapshot',
        'label' => $label,
        'created_by' => (int)$userId,
        'scope' => 'transactions',
    ], false);
    if (!$result['ok']) {
        return $result;
    }
    return [
        'ok' => true,
        'error' => 'Restore point "' . $label . '" was saved.',
        'file' => $name,
        'path' => $path,
    ];
}

function shaven_db_tools_list_files($dir, $kind) {
    if (!is_dir($dir)) {
        return [];
    }
    $files = [];
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
        $metaPath = $path . '.meta.json';
        $meta = [];
        if (is_file($metaPath)) {
            $decoded = json_decode((string)@file_get_contents($metaPath), true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $files[] = [
            'file' => basename($path),
            'path' => $path,
            'bytes' => (int)@filesize($path),
            'mtime' => (int)@filemtime($path),
            'label' => (string)($meta['label'] ?? basename($path)),
            'kind' => (string)($meta['kind'] ?? $kind),
            'created_at' => (string)($meta['created_at'] ?? date('c', (int)@filemtime($path))),
            'created_by' => (int)($meta['created_by'] ?? 0),
            'scope' => (string)($meta['scope'] ?? ''),
            'tables' => is_array($meta['tables'] ?? null) ? $meta['tables'] : [],
        ];
    }
    usort($files, function ($left, $right) {
        return ($right['mtime'] <=> $left['mtime']);
    });
    return $files;
}

function shaven_db_tools_find_file($kind, $file) {
    $file = basename((string)$file);
    if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+\.sql$/', $file)) {
        return null;
    }
    $dir = $kind === 'snapshot' ? shaven_db_tools_snapshot_dir() : shaven_db_tools_backup_dir();
    $path = $dir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        return null;
    }
    return $path;
}

function shaven_db_tools_split_statements($sql) {
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inString = false;
    $stringChar = '';
    $inLineComment = false;
    $inBlockComment = false;
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= $char;
            }
            continue;
        }
        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }
        if (!$inString) {
            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
            if ($char === '\'' || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
            continue;
        }
        $buffer .= $char;
        if ($char === '\\' && $next !== '') {
            $buffer .= $next;
            $i++;
            continue;
        }
        if ($char === $stringChar) {
            $inString = false;
            $stringChar = '';
        }
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function shaven_db_tools_apply_sql($conn, $sql) {
    $statements = shaven_db_tools_split_statements($sql);
    if (!$statements) {
        return ['ok' => false, 'error' => 'The SQL file had no statements to run.'];
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    $ran = 0;
    foreach ($statements as $statement) {
        $upper = strtoupper(ltrim($statement));
        if (strpos($upper, 'SET ') === 0 || strpos($upper, 'USE ') === 0) {
            if (!mysqli_query($conn, $statement)) {
                mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
                return ['ok' => false, 'error' => 'Restore stopped: ' . mysqli_error($conn)];
            }
            $ran++;
            continue;
        }
        if (!mysqli_query($conn, $statement)) {
            $error = mysqli_error($conn);
            mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
            return ['ok' => false, 'error' => 'Restore stopped: ' . $error];
        }
        $ran++;
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
    return ['ok' => true, 'error' => '', 'statements' => $ran];
}

function shaven_db_tools_restore_file($conn, $kind, $file) {
    if (!shaven_db_tools_can()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can restore.'];
    }
    $path = shaven_db_tools_find_file($kind, $file);
    if (!$path) {
        return ['ok' => false, 'error' => 'That file was not found.'];
    }
    $sql = @file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        return ['ok' => false, 'error' => 'The selected file could not be read.'];
    }
    $result = shaven_db_tools_apply_sql($conn, $sql);
    if (!$result['ok']) {
        return $result;
    }
    $label = $kind === 'snapshot' ? 'Restore point' : 'Backup';
    return [
        'ok' => true,
        'error' => $label . ' restored from ' . basename($path) . '.',
    ];
}

function shaven_db_tools_restore_upload($conn, array $upload) {
    if (!shaven_db_tools_can()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can restore.'];
    }
    if (!shaven_db_tools_ensure_dirs()) {
        return ['ok' => false, 'error' => 'The backup folder could not be created.'];
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Choose a valid .sql backup file to upload.'];
    }
    $name = basename((string)($upload['name'] ?? 'upload.sql'));
    if (!preg_match('/\.sql$/i', $name)) {
        return ['ok' => false, 'error' => 'Upload a .sql backup file.'];
    }
    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'The uploaded file was not accepted.'];
    }
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > 80 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Upload a .sql file up to 80 MB.'];
    }
    $savedName = 'upload_' . date('Ymd_His') . '_' . shaven_db_tools_slug(pathinfo($name, PATHINFO_FILENAME)) . '.sql';
    $dest = shaven_db_tools_backup_dir() . DIRECTORY_SEPARATOR . $savedName;
    if (!@move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'error' => 'The uploaded backup could not be saved.'];
    }
    @file_put_contents($dest . '.meta.json', json_encode([
        'kind' => 'backup',
        'label' => 'Uploaded ' . $name,
        'created_by' => (int)($_SESSION['usr_id'] ?? 0),
        'created_at' => date('c'),
        'scope' => 'full',
        'file' => $savedName,
        'bytes' => (int)@filesize($dest),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $restore = shaven_db_tools_restore_file($conn, 'backup', $savedName);
    if (!$restore['ok']) {
        return $restore;
    }
    return [
        'ok' => true,
        'error' => 'Uploaded backup was saved as ' . $savedName . ' and restored.',
        'file' => $savedName,
    ];
}

function shaven_db_tools_delete_file($kind, $file) {
    if (!shaven_db_tools_can()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can delete saved files.'];
    }
    $path = shaven_db_tools_find_file($kind, $file);
    if (!$path) {
        return ['ok' => false, 'error' => 'That file was not found.'];
    }
    @unlink($path . '.meta.json');
    if (!@unlink($path)) {
        return ['ok' => false, 'error' => 'The file could not be deleted.'];
    }
    return ['ok' => true, 'error' => basename($path) . ' was deleted.'];
}

function shaven_db_tools_reset_transactions($conn, $userId, $confirm) {
    if (!shaven_db_tools_can()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can reset transactions.'];
    }
    if (strtoupper(trim((string)$confirm)) !== 'RESET TRANSACTIONS') {
        return ['ok' => false, 'error' => 'Type RESET TRANSACTIONS to confirm the wipe.'];
    }
    $existing = shaven_db_tools_list_tables($conn);
    $targets = [];
    foreach (shaven_db_tools_transaction_tables() as $table) {
        if (in_array($table, $existing, true)) {
            $targets[] = $table;
        }
    }
    if (!$targets) {
        return ['ok' => false, 'error' => 'No transaction tables were found to reset.'];
    }
    $snap = shaven_db_tools_create_snapshot($conn, $userId, 'Auto before reset ' . date('Y-m-d H:i:s'));
    if (!$snap['ok']) {
        return ['ok' => false, 'error' => 'Reset was stopped because an automatic restore point could not be created. ' . $snap['error']];
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    $cleared = [];
    foreach ($targets as $table) {
        if (!mysqli_query($conn, 'DELETE FROM `' . $table . '`')) {
            $error = mysqli_error($conn);
            mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
            return ['ok' => false, 'error' => 'Reset stopped on ' . $table . ': ' . $error];
        }
        mysqli_query($conn, 'ALTER TABLE `' . $table . '` AUTO_INCREMENT=1');
        $cleared[] = $table;
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
    return [
        'ok' => true,
        'error' => 'Transactions and remittances were cleared. Subscriber profiles and plans were kept. Automatic restore point: ' . ($snap['file'] ?? ''),
        'cleared' => $cleared,
        'snapshot' => $snap['file'] ?? '',
    ];
}

function shaven_db_tools_download($kind, $file) {
    if (!shaven_db_tools_can()) {
        return ['ok' => false, 'error' => 'Only an admin or the owner can download these files.'];
    }
    $path = shaven_db_tools_find_file($kind, $file);
    if (!$path) {
        return ['ok' => false, 'error' => 'That file was not found.'];
    }
    return ['ok' => true, 'error' => '', 'path' => $path, 'file' => basename($path)];
}

function shaven_db_tools_counts($conn) {
    $counts = [];
    foreach (array_merge(shaven_db_tools_preserve_tables(), shaven_db_tools_transaction_tables()) as $table) {
        $rows = shaven_rows($conn, 'SELECT COUNT(*) c FROM `' . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . '`');
        $counts[$table] = (int)($rows[0]['c'] ?? 0);
    }
    return $counts;
}
