<?php
header("Content-Type: application/json");

// ================= CONFIG =================
$PLUMIFY_TOKEN = getenv("PLUMIFY_TOKEN"); // definido no Railway
$API_URL = "https://api.plumify.com.br/api/public/v1/transactions";

// ================= INPUT (Sendbot) =================
$amount = 2163; // valor em centavos
$offer_hash = "Z-19RN101IFI26";
$product_hash = "mstjydnuad";

// dados do cliente (exemplo + fallback)
$customer = [
    "name" => $_REQUEST["name"] ?? "Joao Silva",
    "email" => $_REQUEST["email"] ?? "joao@email.com",
    "phone_number" => $_REQUEST["phone"] ?? "11999999999",
    "document" => $_REQUEST["document"] ?? "45780681880"
];

// UTM (somente as 5 do Face)
$tracking = [
    "utm_source"   => $_REQUEST["utm_source"]   ?? null,
    "utm_campaign" => $_REQUEST["utm_campaign"] ?? null,
    "utm_medium"   => $_REQUEST["utm_medium"]   ?? null,
    "utm_term"     => $_REQUEST["utm_term"]     ?? null,
    "utm_content"  => $_REQUEST["utm_content"]  ?? null
];

// ================= PAYLOAD =================
$payload = [
    "amount" => $amount,
    "offer_hash" => $offer_hash,
    "payment_method" => "pix",
    "customer" => $customer,
    "cart" => [
        [
            "product_hash" => $product_hash,
            "title" => "Seguro Prestamista",
            "price" => $amount,
            "quantity" => 1,
            "operation_type" => 1,
            "tangible" => false
        ]
    ],
    "tracking" => $tracking
];

// ================= CURL =================
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $API_URL . "?api_token=" . $PLUMIFY_TOKEN,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ================= ERRO DE CONEXÃO =================
if ($httpCode >= 400 || !$response) {
    echo json_encode([
        "statusCode" => 500,
        "data" => [
            "erro" => "Falha ao chamar Plumify",
            "http_code" => $httpCode,
            "curl_error" => $curlError,
            "response_raw" => $response
        ]
    ]);
    exit;
}

// ================= DECODE =================
$result = json_decode($response, true);

// ================= VALIDAÇÃO =================
if (!isset($result["id"]) || !isset($result["payment_status"])) {
    echo json_encode([
        "statusCode" => 200,
        "data" => [
            "erro" => "Transação não criada",
            "debug" => $result
        ]
    ]);
    exit;
}

// ================= EXTRAÇÃO DO PIX =================
$pix_copia_e_cola = $result["pix"]["pix_qr_code"] ?? null;
$pix_qr_code      = $result["pix"]["pix_url"] ?? null;
$pix_base64       = $result["pix"]["qr_code_base64"] ?? null;

// ================= RESPOSTA PARA O SENDBOT =================
echo json_encode([
    "statusCode" => 200,
    "data" => [
        "transaction_id"   => $result["id"],
        "payment_status"   => $result["payment_status"],
        "pix_copia_e_cola" => $pix_copia_e_cola,
        "pix_qr_code"      => $pix_qr_code,
        "pix_base64"       => $pix_base64
    ]
], JSON_PRETTY_PRINT);
exit;
