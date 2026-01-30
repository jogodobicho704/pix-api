<?php
header("Content-Type: application/json");

echo json_encode([
  "status" => "online",
  "time" => time()
]);
