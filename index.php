<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// =====================
// CONFIG FIXA
// =====================
$AMOUNT = 2163;
$OFFER_HASH = "Z-19RN101IFI26";
$PRODUCT_HASH = "mstjydnuad";
$PRODUCT_NAME = "Seguro Prestamista";

// =====================
// TOKEN PLUMIFY (Railway)
// =====================
$TOKEN = getenv("PLUMIFY_TOKEN");
if (!$TOKEN) {
    http_response_code(500);
    echo json_encode(["erro" => "Token Plumify não configurado"]);
    exit;
}

// =====================
// DADOS DO SENDBOT
// =====================
$name  = $_GET["name"]  ?? "Cliente Pix";
$email = $_GET["email"] ?? "cliente@email.com";
$phone = $_GET["phone"] ?? "11999999999";
$doc   = $_GET["document"] ?? "00000000000";

// =====================
// UTMs (SEND + META + GOOGLE)
// =====================
$tracking = [
    "utm_source"    => $_GET["utm_source"] ?? "",
    "utm_medium"    => $_GET["utm_medium"] ?? "",
    "utm_campaign"  => $_GET["utm_campaign"] ?? "",
    "utm_term"      => $_GET["utm_term"] ?? "",
    "utm_content"   => $_GET["utm_content"] ?? ""
];

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

    "transaction_origin" => "api",
    "tracking" => $tracking
];

// =====================
// REQUEST PLUMIFY
// =====================
$url = "https://api.plumify.com.br/api/public/v1/transactions";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json",
        "Authorization: Bearer $TOKEN"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        "erro" => "Erro cURL",
        "curl_error" => curl_error($ch)
    ]);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

// =====================
// ERRO PLUMIFY
// =====================
if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(500);
    echo json_encode([
        "erro" => "Erro Plumify",
        "http_code" => $httpCode,
        "response" => $result
    ]);
    exit;
}

// =====================
// EXTRAIR PIX (MULTI-ADQUIRENTE)
// =====================
$pixCode = null;
$pixQr   = null;

// payments[]
if (!empty($result["data"]["payments"])) {
    foreach ($result["data"]["payments"] as $payment) {
        if (($payment["method"] ?? "") === "pix" && !empty($payment["pix"])) {
            $pixCode = $payment["pix"]["code"] ?? null;
            $pixQr   = $payment["pix"]["qr_code"] ?? null;
            break;
        }
    }
}

// charges[]
if ((!$pixCode || !$pixQr) && !empty($result["data"]["charges"])) {
    foreach ($result["data"]["charges"] as $charge) {
        if (($charge["payment_method"] ?? "") === "pix") {
            $pixCode = $charge["pix_code"] ?? null;
            $pixQr   = $charge["pix_qr_code"] ?? null;
            break;
        }
    }
}

// fallback direto
if (!$pixCode && isset($result["data"]["pix"]["code"])) {
    $pixCode = $result["data"]["pix"]["code"];
    $pixQr   = $result["data"]["pix"]["qr_code"] ?? null;
}

// =====================
// RETORNO FINAL SENDBOT
// =====================
echo json_encode([
    "pix_copia_e_cola" => $pixCode,
    "pix_qr_code"      => $pixQr,
    "transaction_id"  => $result["data"]["id"] ?? null
]);
exit;
