<?php
session_start();

// Initiale Schülerliste
if (!isset($_SESSION['schueler'])) {
    $_SESSION['schueler'] = [
            ["name" => "Anna",  "noten" => ["Mathe" => 3, "Deutsch" => 2, "Englisch" => 1]],
            ["name" => "Ben",   "noten" => ["Mathe" => 4, "Deutsch" => 3, "Englisch" => 2]],
            ["name" => "Clara", "noten" => ["Mathe" => 2, "Deutsch" => 1, "Englisch" => 1]]
    ];
}

$schueler = &$_SESSION['schueler'];

// --- Funktion: Durchschnitt berechnen ---
function calculateAverage(array $noten): float {
    return array_sum($noten) / count($noten);
}

// --- Funktion: bester Schüler ---
function findBestStudent(array $schueler): array {
    $bester = null;
    $besterSchnitt = PHP_FLOAT_MAX;
    foreach ($schueler as $one) {
        $avg = calculateAverage($one["noten"]);
        if ($avg < $besterSchnitt) {
            $besterSchnitt = $avg;
            $bester = ["name" => $one["name"], "schnitt" => $avg];
        }
    }
    return $bester;
}

// --- Durchschnitt pro Fach ---
function averagePerSubject(array $schueler, string $fach): ?float {
    $noten = [];
    foreach ($schueler as $s) {
        if (isset($s['noten'][$fach])) $noten[] = $s['noten'][$fach];
    }
    if (count($noten) === 0) return null;
    return array_sum($noten) / count($noten);
}

// --- Neue Schülerdaten verarbeiten ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $noten = [
            "Mathe" => (float)($_POST['Mathe'] ?? 0),
            "Deutsch" => (float)($_POST['Deutsch'] ?? 0),
            "Englisch" => (float)($_POST['Englisch'] ?? 0)
    ];
    if ($name !== '') {
        $schueler[] = ["name" => $name, "noten" => $noten];
    }
}

$bester = findBestStudent($schueler);
$subjects = array_keys($schueler[0]['noten']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schülerverwaltung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container">
    <h1 class="mb-4">Schüler Noten</h1>

    <div class="row">
        <!-- Tabelle links -->
        <div class="col-md-8">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                <tr><th>Name</th><th>Durchschnitt</th></tr>
                </thead>
                <tbody>
                <?php foreach ($schueler as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= round(calculateAverage($s['noten']),2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Durchschnitt pro Fach -->
            <div class="row mt-3">
                <?php foreach ($subjects as $fach): ?>
                    <?php $avg = round(averagePerSubject($schueler, $fach),2); ?>
                    <div class="col-md">
                        <div class="card text-center border-primary mb-2">
                            <div class="card-header bg-primary text-white"><?= htmlspecialchars($fach) ?></div>
                            <div class="card-body">
                                <p class="card-text">Durchschnitt: <strong><?= $avg ?></strong></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bester Schüler Karte rechts -->
        <div class="col-md-4">
            <div class="card text-center border-success">
                <div class="card-header bg-success text-white">Bester Schüler</div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($bester['name']) ?></h5>
                    <p class="card-text">Schnitt: <strong><?= round($bester['schnitt'],2) ?></strong></p>
                </div>
            </div>

            <!-- Formular neue Schüler -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">➕ Neuer Schüler</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-2">
                            <label>
                                <input type="text" name="name" class="form-control" placeholder="Name" required>
                            </label>
                        </div>
                        <?php foreach ($subjects as $fach): ?>
                            <div class="mb-2">
                                <label>
                                    <input type="number" name="<?= $fach ?>" class="form-control" placeholder="<?= $fach ?>" min="1" max="5" required>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary w-100">Hinzufügen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Info-Bereich Speicheroptionen -->
    <div class="alert alert-info mt-4">
        <h5>Speicherung über mehrere Seitenaktualisierungen:</h5>
        <ul>
            <li><strong>Session:</strong> Temporär während der Session verfügbar.</li>
            <li><strong>Datei/CSV/JSON:</strong> Dauerhafte Speicherung auf dem Server.</li>
            <li><strong>Datenbank:</strong> Dauerhafte, flexible Speicherung mit MySQL/SQLite.</li>
        </ul>
    </div>
</div>
</body>
</html>
