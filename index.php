<?php

header("Content-Type: application/json");

// ========= RECEBE JSON =========
$input = json_decode(file_get_contents("php://input"), true);

// UTMs (vindas do Sendbot)
$utm_source   = $input['utm_source']   ?? 'direct';
$utm_medium   = $input['utm_medium']   ?? '';
$utm_campaign = $input['utm_campaign'] ?? '';
$utm_content  = $input['utm_content']  ?? '';

// ========= CONFIG =========
$apiUrl   = "https://api.plumify.com.br/v1/transactions";
$apiToken = getenv("PLUMIFY_TOKEN");

// ========= PAYLOAD =========
$payload = [
    "amount" => 2700, // R$27,00
    "offer_hash" => "SEU_OFFER_HASH",
    "payment_method" => "pix",

    "customer" => [
        "name" => "Cliente Pix",
        "email" => "cliente@email.com",
        "phone_number" => "51999999999",
        "document" => "00000000000",
        "street_name" => "Rua Teste",
        "number" => "123",
        "complement" => "",
        "neighborhood" => "Centro",
        "city" => "Porto Alegre",
        "state" => "RS",
        "zip_code" => "90000000"
    ],

    "cart" => [
        [
            "product_hash" => "SEU_PRODUCT_HASH",
            "title" => "Taxa Vivência",
            "cover" => null,
            "price" => 2700,
            "quantity" => 1,
            "operation_type" => 1,
            "tangible" => false
        ]
    ],

    "expire_in_days" => 1,
    "transaction_origin" => "api",

    "tracking" => [
        "utm_source" => $utm_source,
        "utm_medium" => $utm_medium,
        "utm_campaign" => $utm_campaign,
        "utm_content" => $utm_content
    ]
];

// ========= CURL =========
$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $apiToken",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ========= TRATAMENTO =========
if ($httpCode >= 400 || !$response) {
    http_response_code(500);
    echo json_encode(["error" => "Erro ao gerar Pix"]);
    exit;
}

$data = json_decode($response, true);

// ========= RETORNO LIMPO =========
echo json_encode([
    "pix_payload" => $data['pix']['copia_e_cola'] ?? null,
    "valor" => "27,00"
]);
