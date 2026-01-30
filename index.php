<?php
header("Content-Type: application/json");

// ================= CONFIG =================
$PLUMIFY_TOKEN = getenv("PLUMIFY_TOKEN"); // definido no Railway
$API_URL = "https://api.plumify.com.br/api/public/v1/transactions";

// Meta CAPI
$PIXEL_ID = "SEU_PIXEL_ID";
$ACCESS_TOKEN = "SEU_ACCESS_TOKEN";

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

// ================= PAYLOAD PLUMIFY =================
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

// ================= CURL PLUMIFY =================
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
        "erro" => "Falha ao chamar Plumify",
        "http_code" => $httpCode,
        "curl_error" => $curlError,
        "response_raw" => $response
    ]);
    exit;
}

// ================= DECODE PLUMIFY =================
$result = json_decode($response, true);

// ================= VALIDAÇÃO =================
if (!isset($result["id"]) || !isset($result["payment_status"])) {
    echo json_encode([
        "erro" => "Transação não criada",
        "debug" => $result
    ]);
    exit;
}

// ================= EXTRAÇÃO DO PIX =================
$pix_copia_e_cola = $result["pix"]["pix_qr_code"] ?? null;
$pix_qr_code      = $result["pix"]["pix_url"] ?? null;
$pix_base64       = $result["pix"]["qr_code_base64"] ?? null;

// ================= HASH PARA CAPI =================
function hash_sha256($value) {
    return hash('sha256', strtolower(trim($value)));
}

$user_data = [
    "em" => hash_sha256($customer["email"]),
    "ph" => hash_sha256($customer["phone_number"]),
    "fn" => hash_sha256($customer["name"])
];

$custom_data = [
    "currency" => "BRL",
    "value" => $amount / 100,
    "transaction_id" => $result["id"],
    "utm_source" => $tracking["utm_source"],
    "utm_campaign" => $tracking["utm_campaign"],
    "utm_medium" => $tracking["utm_medium"],
    "utm_term" => $tracking["utm_term"],
    "utm_content" => $tracking["utm_content"]
];

$event_payload = [
    "data" => [
        [
            "event_name" => "Purchase",
            "event_time" => time(),
            "event_id" => $result["id"],
            "user_data" => $user_data,
            "custom_data" => $custom_data,
            "action_source" => "website"
        ]
    ]
];

// ================= ENVIO CAPI =================
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v17.0/{$PIXEL_ID}/events?access_token={$ACCESS_TOKEN}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($event_payload)
]);
$response_meta = curl_exec($ch);
$meta_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ================= RESPOSTA PARA O SENDBOT =================
echo json_encode([
    "transaction_id"   => $result["id"],
    "payment_status"   => $result["payment_status"],
    "pix_copia_e_cola" => $pix_copia_e_cola,
    "pix_qr_code"      => $pix_qr_code,
    "pix_base64"       => $pix_base64,
    "meta_capi_response" => $response_meta,
    "meta_capi_http" => $meta_http
]);
exit;
