<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// TOKEN vindo do Railway
$token = getenv("PLUMIFY_TOKEN");

if (!$token) {
    http_response_code(500);
    echo json_encode(["erro" => "Token Plumify não configurado"]);
    exit;
}

// Endpoint correto
$url = "https://api.plumify.com.br/api/public/v1/transactions?api_token=H0uDeO6yO5F2RFGVSrloOF2KNB3dj7NSKDi9fX7qIf7Cq1YEiv9vITWE8QSu" . $token;

// Payload em ARRAY (PHP)
$payload = [
    "amount" => 2163,
    "offer_hash" => "Z-19RN101IFI26",
    "payment_method" => "pix",

    "customer" => [
        "name" => $_GET["name"] ?? "Cliente Pix",
        "email" => $_GET["email"] ?? "cliente@email.com",
        "phone_number" => $_GET["phone"] ?? "11999999999",
        "document" => $_GET["document"] ?? "00000000000"
    ],

    "cart" => [
        [
            "product_hash" => "mstjydnuad",
            "title" => "Seguro Prestamista",
            "price" => 2163,
            "quantity" => 1,
            "operation_type" => 1,
            "tangible" => false
        ]
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo json_encode([
        "erro" => "Erro no cURL",
        "curl_error" => curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);

// Se deu erro na API
if ($httpCode < 200 || $httpCode >= 300) {
    echo json_encode([
        "erro" => "Erro Plumify",
        "http_code" => $httpCode,
        "response" => $result
    ]);
    exit;
}

/*
  ⬇️ RETORNAMOS APENAS O PIX (Sendbot friendly)
*/
echo json_encode([
    "pix_qr_code" => $result["data"]["pix"]["qr_code"] ?? null,
    "pix_copia_e_cola" => $result["data"]["pix"]["code"] ?? null,
    "transaction_id" => $result["data"]["id"] ?? null
]);
