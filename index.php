<?php
header("Content-Type: application/json");

// ================= CONFIG =================
$PLUMIFY_TOKEN = getenv("PLUMIFY_TOKEN");
$API_URL = "https://api.plumify.com.br/api/public/v1/transactions";

// META
$META_DATASET_ID = "1607272920288929";
$META_ACCESS_TOKEN = "EAARFzDZBZCLZBgBQuw9xZCtVqBAfpn91GuZAcqGDRJ2lmyiGntn500xXM8NomL7kikRpM63U8Y7SoDRkqxB1LZCkKVaeoxPifiKFNKK1aVZAdoSFydAYJXE596JvBIHhAyQrZBg3nHhmCZCJT8RPyswXxKrhIX46JYQjteaW8lcZCBDxzZBdEKsUKWMdY44dpYTeAZDZD";

// ================= EVENT ID (DEDUP CORRETO) =================
$event_id = $_REQUEST['event_id'] ?? ('pix_' . uniqid());

// ================= INPUT =================
$amount = 2163; // centavos
$offer_hash = "Z-19RN101IFI26";
$product_hash = "mstjydnuad";

$customer = [
    "name"         => $_REQUEST["name"] ?? "",
    "email"        => $_REQUEST["email"] ?? "",
    "phone_number" => $_REQUEST["phone_number"] ?? "",
    "document"     => $_REQUEST["document"] ?? ""
];

// ================= TRACKING (REMOVE NULOS) =================
$tracking = array_filter([
    "utm_source"   => $_REQUEST["utm_source"]   ?? null,
    "utm_campaign" => $_REQUEST["utm_campaign"] ?? null,
    "utm_medium"   => $_REQUEST["utm_medium"]   ?? null,
    "utm_term"     => $_REQUEST["utm_term"]     ?? null,
    "utm_content"  => $_REQUEST["utm_content"]  ?? null,
    "fbclid"       => $_REQUEST["fbclid"]       ?? null
]);

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
    ]
];

if (!empty($tracking)) {
    $payload["tracking"] = $tracking;
}

// ================= CURL PLUMIFY =================
$ch = curl_init($API_URL . "?api_token=" . $PLUMIFY_TOKEN);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode >= 400 || !$response) {
    echo json_encode([
        "erro" => "Falha ao chamar Plumify",
        "http_code" => $httpCode,
        "curl_error" => $curlError
    ]);
    exit;
}

$result = json_decode($response, true);

// ================= VALIDA =================
if (!isset($result["id"])) {
    echo json_encode([
        "erro" => "Transação não criada",
        "debug" => $result
    ]);
    exit;
}

// ================= PIX =================
$responseData = [
    "transaction_id" => $result["id"],
    "event_id"       => $event_id,
    "payment_status" => $result["payment_status"] ?? "pending",
    "pix_copia_e_cola" => $result["pix"]["pix_qr_code"] ?? null,
    "pix_qr_code"      => $result["pix"]["pix_url"] ?? null,
    "pix_base64"       => $result["pix"]["qr_code_base64"] ?? null
];

// ================= META CAPI (SÓ SE PAGO) =================
if (($result["payment_status"] ?? null) === "paid") {

    $user_data = array_filter([
        "em" => !empty($customer["email"]) ? hash('sha256', strtolower(trim($customer["email"]))) : null,
        "ph" => !empty($customer["phone_number"]) ? hash('sha256', preg_replace('/\D/', '', $customer["phone_number"])) : null,
        "external_id" => !empty($tracking["fbclid"]) ? hash('sha256', $tracking["fbclid"]) : null,
        "client_ip_address" => $_SERVER['REMOTE_ADDR'] ?? null,
        "client_user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
        "fbp" => $_COOKIE['_fbp'] ?? null,
        "fbc" => $_COOKIE['_fbc'] ?? null
    ]);

    $capi_payload = [
        "data" => [[
            "event_name"    => "Purchase",
            "event_time"    => time(),
            "event_id"      => $event_id,
            "action_source" => "website",
            "user_data"     => $user_data,
            "custom_data"   => [
                "currency" => "BRL",
                "value"    => $amount / 100
            ]
        ]]
    ];

    $chMeta = curl_init("https://graph.facebook.com/v18.0/{$META_DATASET_ID}/events?access_token={$META_ACCESS_TOKEN}");
    curl_setopt_array($chMeta, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($capi_payload)
    ]);
    curl_exec($chMeta);
    curl_close($chMeta);
}

// ================= RETORNO FINAL =================
echo json_encode([
    "statusCode" => 200,
    "data" => $responseData
]);
exit;
