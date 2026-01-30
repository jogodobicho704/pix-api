<?php

header("Content-Type: application/json");

// =====================
// CONFIG FIXA DO PRODUTO
// =====================
$AMOUNT = 2163; // centavos (R$21,63)
$OFFER_HASH = "Z-19RN101IFI26";
$PRODUCT_HASH = "mstjydnuad";
$PRODUCT_NAME = "Seguro Prestamista";

// =====================
// TOKEN
// =====================
$TOKEN = getenv("PLUMIFY_TOKEN");
if (!$TOKEN) {
    http_response_code(500);
    echo json_encode(["error" => "Token Plumify não configurado"]);
    exit;
}

// =====================
// DADOS VIA GET (SENDBOT)
// =====================
$name  = $_GET['name']  ?? "Cliente";
$email = $_GET['email'] ?? "cliente@email.com";
$phone = $_GET['phone'] ?? "51999999999";
$doc   = $_GET['doc']   ?? "00000000000";

// UTMs (opcional)
$utm_source   = $_GET['utm_source']   ?? "";
$utm_medium   = $_GET['utm_medium']   ?? "";
$utm_campaign = $_GET['utm_campaign'] ?? "";
$utm_term     = $_GET['utm_term']     ?? "";
$utm_content  = $_GET['utm_content']  ?? "";

// =====================
// PAYLOAD PLUMIFY
// =====================
$payload = [
    "amount" => $AMOUNT,
    "offer_hash" => $OFFER_HASH,
    "payment_method" => "pix",
    "customer" => [
        "name" => $name,
        "email" => $email,
        "phone_number" => $phone,
        "document" => $doc
    ],
    "cart" => [
        [
            "product_hash" => $PRODUCT_HASH,
            "title" => $PRODUCT_NAME,
            "price" => $AMOUNT,
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
        "utm_term" => $utm_term,
        "utm_content" => $utm_content
    ]
];

// =====================
// REQUEST PARA PLUMIFY
// =====================
$ch = curl_init("https://api.plumify.com.br/v1/checkout");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $TOKEN",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 400 || !$response) {
    http_response_code(500);
    echo json_encode([
        "erro" => "Falha ao chamar Plumify",
        "http_code" => $httpCode,
        "response_raw" => $response
    ]);
    exit;
}

$data = json_decode($response, true);

// =====================
// RETORNO LIMPO PRO SENDBOT
// =====================
echo json_encode([
    "pix_copia_e_cola" => $data['data']['pix']['code'] ?? null,
    "qr_code_base64"   => $data['data']['pix']['qr_code'] ?? null,
    "transaction_id"  => $data['data']['id'] ?? null
]);
