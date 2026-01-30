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
// TOKEN PLUMIFY
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
// UTMs (APENAS AS 5)
// =====================
$tracking = [
    "utm_source"   => $_GET["utm_source"]   ?? "",
    "utm_medium"   => $_GET["utm_medium"]   ?? "",
    "utm_campaign" => $_GET["utm_campaign"] ?? "",
    "utm_term"     => $_GET["utm_term"]     ?? "",
    "utm_content"  => $_GET["utm_content"]  ?? ""
];

// =====================
// CRIA TRANSAÇÃO
// =====================
$payload = [
    "api_token" => $TOKEN,
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

$createUrl = "https://api.plumify.com.br/api/public/v1/transactions";

$ch = curl_init($createUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode(["erro" => curl_error($ch)]);
    exit;
}

curl_close($ch);
$result = json_decode($response, true);

// =====================
// IDENTIFICAR ID DA TRANSAÇÃO
// =====================
$transactionId =
    $result["data"]["id"] ??
    $result["data"]["transaction_id"] ??
    $result["data"]["reference"] ??
    null;

if (!$transactionId) {
    echo json_encode([
        "erro" => "ID da transação não retornado",
        "debug" => $result
    ]);
    exit;
}

// =====================
// POLLING – CONSULTA PIX
// =====================
$pixCode = null;
$pixQr   = null;

for ($i = 0; $i < 5; $i++) { // tenta por até ~5 segundos
    sleep(1);

    $getUrl = "https://api.plumify.com.br/api/public/v1/transactions/$transactionId?api_token=$TOKEN";

    $ch = curl_init($getUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $check = curl_exec($ch);
    curl_close($ch);

    $checkResult = json_decode($check, true);

    if (!empty($checkResult["data"]["data"])) {
        foreach ($checkResult["data"]["data"] as $item) {
            if (
                isset($item["pix"]) &&
                !empty($item["pix"]["pix_code"]) &&
                !empty($item["pix"]["pix_qr_code"])
            ) {
                $pixCode = $item["pix"]["pix_code"];
                $pixQr   = $item["pix"]["pix_qr_code"];
                break 2;
            }
        }
    }
}

// =====================
// RETORNO SENDBOT
// =====================
echo json_encode([
    "pix_copia_e_cola" => $pixCode,
    "pix_qr_code"      => $pixQr,
    "transaction_id"  => $transactionId
]);
exit;
