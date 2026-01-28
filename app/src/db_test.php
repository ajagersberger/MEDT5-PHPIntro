<?php
// DB-Verbindungsdaten aus Umgebungsvariablen oder Default-Werten
$cfg = [
    'host' => getenv('MYSQL_HOST') !== false ? getenv('MYSQL_HOST') : 'db',
    'port' => getenv('MYSQL_PORT') !== false ? (int)getenv('MYSQL_PORT') : 3306,
    'user' => getenv('MYSQL_USER') !== false ? getenv('MYSQL_USER') : 'devuser',
    'pass' => getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : 'devpass',
    'db'   => getenv('MYSQL_DATABASE') !== false ? getenv('MYSQL_DATABASE') : 'test_db',
];

// Hosts to try: zuerst konfigurierter Host, dann Fallback auf localhost:9906 (für lokalen Zugriff)
$tries = [
    ['host' => $cfg['host'], 'port' => $cfg['port']],
    ['host' => '127.0.0.1',  'port' => 9906],
];

if (!extension_loaded('pdo_mysql')) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>DB Test</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="p-4"><div class="container">';
    echo '<div class="alert alert-danger mb-0"><strong>pdo_mysql fehlt:</strong> PHP-Erweiterung <code>pdo_mysql</code> ist nicht installiert/aktiv.';
    echo '<br>Fix: Dockerfile muss <code>docker-php-ext-install pdo pdo_mysql</code> enthalten und neu gebaut werden: <code>docker compose up -d --build</code>.';
    echo '</div></div></body></html>';
    exit;
}

$connected = false;
$pdo = null;
$errors = [];
$used = null;

// Protokoll für Ausgabe
$log = [];
$crud = [
    'table' => null,
    'rows' => [],
    'counts' => [],
    'errors' => [],
];

// Cleanup-Plan (DROP nach Ausgabe)
$cleanup = [
    'scheduled' => false,
    'table' => null,
    'error' => null,
];

foreach ($tries as $t) {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $t['host'],
        $t['port'],
        $cfg['db']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $connected = true;
        $used = $t;
        break;
    } catch (Throwable $e) {
        $errors[] = "Host {$t['host']}:{$t['port']} -> " . $e->getMessage();
    }
}

if ($connected) {
    // eindeutiger Tabellenname pro Lauf; bleibt kurz und MySQL-sicher
    $runId = bin2hex(random_bytes(4));
    $table = "dbtest_tmp_" . $runId;
    $crud['table'] = $table;

    // Cleanup nach Request-Ende (nach HTML-Ausgabe)
    $cleanup['scheduled'] = true;
    $cleanup['table'] = $table;
    $cleanupPdo = $pdo; // PDO-Handle ins Shutdown mitnehmen
    $cleanupTable = $table;
    register_shutdown_function(function () use (&$cleanup, $cleanupPdo, $cleanupTable) {
        try {
            if ($cleanupPdo instanceof PDO) {
                $cleanupPdo->exec("DROP TABLE IF EXISTS `$cleanupTable`");
            }
        } catch (Throwable $e) {
            // Nicht mehr in HTML sichtbar; trotzdem in $cleanup ablegen (falls noch erreichbar)
            $cleanup['error'] = $e->getMessage();
        }
    });

    $exec = function(string $sql, array $params = []) use (&$pdo, &$log) {
        $t0 = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $log[] = ['sql' => $sql, 'params' => $params, 'ms' => $ms, 'rowCount' => $stmt->rowCount()];
        return $stmt;
    };

    $fetchAll = function(string $sql, array $params = []) use ($exec) {
        return $exec($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    };

    try {
        $crud['counts']['committed'] = null;

        // 1) Tabelle anlegen
        $exec("
            CREATE TABLE `$table` (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              name VARCHAR(100) NOT NULL,
              qty INT NOT NULL DEFAULT 0,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 2) Einträge erstellen
        $exec("INSERT INTO `$table` (name, qty) VALUES (?, ?)", ['Apfel', 10]);
        $firstId = (int)$pdo->lastInsertId();
        $exec("INSERT INTO `$table` (name, qty) VALUES (?, ?)", ['Birne', 5]);
        $secondId = (int)$pdo->lastInsertId();
        $crud['counts']['inserted_ids'] = [$firstId, $secondId];

        // 3) Anzeigen
        $crud['rows']['after_insert'] = $fetchAll("SELECT id, name, qty FROM `$table` ORDER BY id");

        // 4) Einträge ändern
        $exec("UPDATE `$table` SET qty = qty + 7 WHERE id = ?", [$firstId]);
        $crud['rows']['after_update'] = $fetchAll("SELECT id, name, qty FROM `$table` ORDER BY id");

        // 5) Einträge löschen
        $exec("DELETE FROM `$table` WHERE id = ?", [$secondId]);
        $crud['rows']['after_delete'] = $fetchAll("SELECT id, name, qty FROM `$table` ORDER BY id");

        $crud['counts']['ok'] = true;
        // DROP erfolgt bewusst NICHT hier, sondern im Shutdown (nach Ausgabe)
        $crud['counts']['dropped'] = null;
    } catch (Throwable $e) {
        $crud['errors'][] = $e->getMessage();
        $crud['counts']['ok'] = false;
        $crud['counts']['dropped'] = null;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>DB Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h1 class="mb-3">MySQL DB-Test</h1>

    <?php if ($connected): ?>
        <div class="alert alert-success">
            Verbunden mit MySQL unter <strong><?php echo htmlspecialchars($used['host'] . ':' . $used['port']); ?></strong>
            als Benutzer <strong><?php echo htmlspecialchars($cfg['user']); ?></strong>.
        </div>

        <h5>Kurze Abfragen</h5>
        <ul>
            <?php
            $row = $pdo->query("SELECT DATABASE() AS dbname")->fetch();
            ?>
            <li>Aktuelle Datenbank: <strong><?php echo htmlspecialchars($row['dbname'] ?? '—'); ?></strong></li>

            <?php
            $row = $pdo->query("SELECT CURRENT_USER() AS user")->fetch();
            ?>
            <li>MySQL CURRENT_USER(): <strong><?php echo htmlspecialchars($row['user'] ?? '—'); ?></strong></li>
        </ul>

        <h5 class="mt-3">Tabellen</h5>
        <?php
            $tables = [];
            $tablesError = null;

            try {
                $rows = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
                $tables = array_map(static fn($r) => (string)$r[0], $rows);
                sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
            } catch (Throwable $e) {
                $tablesError = $e->getMessage();
            }

            $tablesTotal = count($tables);
            $tablesShown = min($tablesTotal, 50);
            $tables = array_slice($tables, 0, 50);
        ?>

        <?php if ($tablesError): ?>
            <div class="alert alert-warning">
                Tabellen konnten nicht geladen werden: <code><?php echo htmlspecialchars($tablesError); ?></code>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <div class="text-muted small">
                    Gesamt: <strong><?php echo (int)$tablesTotal; ?></strong>
                    <?php if ($tablesTotal > 50): ?>
                        (angezeigt: <strong><?php echo (int)$tablesShown; ?></strong>)
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($tablesTotal === 0): ?>
                <div class="alert alert-secondary mb-3">Keine Tabellen vorhanden.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-2 mb-3">
                    <?php foreach ($tables as $t): ?>
                        <div class="col">
                            <div class="card h-100">
                                <div class="card-body py-2">
                                    <span class="badge text-bg-primary text-wrap w-100 text-start">
                                        <?php echo htmlspecialchars($t); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <h5 class="mt-4">CRUD-Test (create/insert/update/delete/drop)</h5>
        <div class="card mb-3">
            <div class="card-body">
                <div>Testtabelle: <code><?php echo htmlspecialchars($crud['table'] ?? '—'); ?></code></div>
                <?php
                    $ok = !empty($crud['counts']['ok']);
                    // Drop passiert nach Ausgabe -> nur "geplant" anzeigen
                    $dropPlanned = !empty($cleanup['scheduled']) && !empty($cleanup['table']);
                ?>
                <div>Status: <strong><?php echo ($ok && $dropPlanned) ? 'OK (CRUD; Drop nach Ausgabe geplant)' : 'FEHLER'; ?></strong></div>
                <div class="text-muted small">
                    ok=<?php echo $ok ? 'true' : 'false'; ?>,
                    drop_planned=<?php echo $dropPlanned ? 'true' : 'false'; ?>
                </div>
                <?php if (!empty($crud['errors'])): ?>
                    <div class="text-danger mt-2"><pre class="mb-0"><?php echo htmlspecialchars(implode("\n", $crud['errors'])); ?></pre></div>
                <?php endif; ?>
                <?php if (!empty($cleanup['error'])): ?>
                    <div class="text-danger mt-2"><pre class="mb-0"><?php echo htmlspecialchars("Cleanup fehlgeschlagen: " . $cleanup['error']); ?></pre></div>
                <?php endif; ?>
            </div>
        </div>

        <?php
            // Helper: Rows als Bootstrap-Tabelle rendern (minimal)
            $renderRowsTable = function (array $rows) {
                if (count($rows) === 0) {
                    echo '<div class="text-muted small">Keine Rows.</div>';
                    return;
                }
                $cols = array_keys($rows[0]);
                echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">';
                echo '<thead><tr>';
                foreach ($cols as $c) echo '<th scope="col">' . htmlspecialchars((string)$c) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    echo '<tr>';
                    foreach ($cols as $c) {
                        $v = $r[$c] ?? null;
                        echo '<td><code>' . htmlspecialchars($v === null ? 'NULL' : (string)$v) . '</code></td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            };

            $rowsAfterInsert = $crud['rows']['after_insert'] ?? [];
            $rowsAfterUpdate = $crud['rows']['after_update'] ?? [];
            $rowsAfterDelete = $crud['rows']['after_delete'] ?? [];
        ?>

        <!-- ersetzt: Rows nach INSERT/UPDATE/DELETE + SQL-Protokoll (var_dump in <pre>) -->
        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">Rows nach INSERT</div>
                    <div class="card-body">
                        <?php $renderRowsTable($rowsAfterInsert); ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">Rows nach UPDATE</div>
                    <div class="card-body">
                        <?php $renderRowsTable($rowsAfterUpdate); ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">Rows nach DELETE</div>
                    <div class="card-body">
                        <?php $renderRowsTable($rowsAfterDelete); ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>SQL-Protokoll</span>
                        <span class="badge text-bg-secondary"><?php echo (int)count($log); ?> Statements</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($log) === 0): ?>
                            <div class="text-muted small">Kein Log.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>SQL</th>
                                            <th style="width: 30%;">Params</th>
                                            <th class="text-end" style="width: 90px;">ms</th>
                                            <th class="text-end" style="width: 120px;">rowCount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($log as $entry): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars((string)($entry['sql'] ?? '')); ?></code></td>
                                            <td class="small">
                                                <code><?php echo htmlspecialchars(json_encode($entry['params'] ?? [], JSON_UNESCAPED_UNICODE)); ?></code>
                                            </td>
                                            <td class="text-end"><code><?php echo htmlspecialchars((string)($entry['ms'] ?? '')); ?></code></td>
                                            <td class="text-end"><code><?php echo htmlspecialchars((string)($entry['rowCount'] ?? '')); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php $pdo = null; ?>

    <?php else: ?>
        <div class="alert alert-danger">
            Verbindung fehlgeschlagen. Versuchte Ziele:
            <pre class="bg-light p-2"><?php echo htmlspecialchars(implode("\n", $errors)); ?></pre>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
