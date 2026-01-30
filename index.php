<?php

header("Content-Type: application/json");

if (isset($_GET['test'])) {
    echo json_encode([
        "status" => "online",
        "php" => phpversion()
    ]);
    exit;
}

echo json_encode([
    "msg" => "endpoint ativo"
]);
