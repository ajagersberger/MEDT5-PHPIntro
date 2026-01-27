
<?php
// boilerplate.php

$array = [
    [
        "title" => "Apfel",
        "desc" => "Ein Apfel ist sehr gesund !"
    ],
    [
        "title" => "IDE",
        "desc" => "Damit kann man Programmieren lernen !"
    ]
];
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Array-Ausgabe mit var_dump</title>
    https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Array mit var_dump</h1>
        <div class="card">
            <div class="card-body">
                <pre><?php var_dump($array); ?></pre>
            </div>
        </div>
    </div>
</body>
</html>
