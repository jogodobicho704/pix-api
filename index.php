<?php
// ================= EVENT ID ÚNICO =================
$event_id = 'purchase_' . uniqid();

header("Content-Type: application/json");

// ================= CONFIG =================
$PLUMIFY_TOKEN = getenv("PLUMIFY_TOKEN");
$API_URL = "https://api.plumify.com.br/api/public/v1/transactions";

// META – DATASET CONFIRMADO
$META_DATASET_ID = "1607272920288929";
$META_ACCESS_TOKEN = "EAARFzDZBZCLZBgBQuw9xZCtVqBAfpn91GuZAcqGDRJ2lmyiGntn500xXM8NomL7kikRpM63U8Y7SoDRkqxB1LZCkKVaeoxPifiKFNKK1aVZAdoSFydAYJXE596JvBIHhAyQrZBg3nHhmCZCJT8RPyswXxKrhIX46JYQjteaW8lcZCBDxzZBdEKsUKWMdY44dpYTeAZDZD";

// ================= INPUT =================
$amount = 2163; // centavos
$offer_hash = "Z-19RN101IFI26";
$product_hash = "mstjydnuad";

$customer = [
    "name"         => $_REQUEST["name"] ?? null,
    "email"        => $_REQUEST["email"] ?? null,
    "phone_number" => $_REQUEST["phone_number"] ?? null,
    "document"     => $_REQUEST["document"] ?? null
];

$tracking = [
    "utm_source"   => $_REQUEST["utm_source"] ?? null,
    "utm_campaign" => $_REQUEST["utm_campaign"] ?? null,
    "utm_medium"   => $_REQUEST["utm_medium"] ?? null,
    "utm_term"     => $_REQUEST["utm_term"] ?? null,
    "utm_content"  => $_REQUEST["utm_content"] ?? null,
    "fbclid"       => $_REQUEST["fbclid"] ?? null
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

if (!isset($result["id"], $result["payment_status"])) {
    echo json_encode([
        "erro" => "Transação não criada",
        "debug" => $result
    ]);
    exit;
}

// ================= PIX =================
$pix_copia_e_cola = $result["pix"]["pix_qr_code"] ?? null;
$pix_qr_code     = $result["pix"]["pix_url"] ?? null;
$pix_base64      = $result["pix"]["qr_code_base64"] ?? null;

// ================= RESPOSTA =================
$responseData = [
    "transaction_id"   => $result["id"],
    "event_id"         => $event_id,
    "payment_status"   => $result["payment_status"],
    "pix_copia_e_cola" => $pix_copia_e_cola,
    "pix_qr_code"      => $pix_qr_code,
    "pix_base64"       => $pix_base64
];

// ================= META CAPI (DATASET) =================
if ($result["payment_status"] === "paid") {

    $user_data = [];

    if (!empty($customer["email"])) {
        $user_data["em"] = hash('sha256', strtolower(trim($customer["email"])));
    }

    if (!empty($customer["phone_number"])) {
        $user_data["ph"] = hash('sha256', preg_replace('/\D/', '', $customer["phone_number"]));
    }

    if (!empty($tracking["fbclid"])) {
        $user_data["external_id"] = hash('sha256', $tracking["fbclid"]);
    }

    $user_data["client_ip_address"] = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_data["client_user_agent"] = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $user_data["fbp"] = $_COOKIE['_fbp'] ?? null;
    $user_data["fbc"] = $_COOKIE['_fbc'] ?? null;

    $capi_payload = [
        "data" => [
            [
                "event_name"    => "Purchase",
                "event_time"    => time(),
                "event_id" => $event_id,
                "action_source" => "website",
                "user_data"     => $user_data,
                "custom_data"   => [
                    "currency"      => "BRL",
                    "value"         => $amount / 100,
                    "utm_source"    => $tracking["utm_source"],
                    "utm_medium"    => $tracking["utm_medium"],
                    "utm_campaign"  => $tracking["utm_campaign"],
                    "utm_content"   => $tracking["utm_content"],
                    "utm_term"      => $tracking["utm_term"]
                ]
            ]
        ]
    ];

    $chMeta = curl_init();
    curl_setopt_array($chMeta, [
        CURLOPT_URL => "https://graph.facebook.com/v18.0/{$META_DATASET_ID}/events?access_token={$META_ACCESS_TOKEN}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($capi_payload)
    ]);

    $meta_response = curl_exec($chMeta);
    $meta_http     = curl_getinfo($chMeta, CURLINFO_HTTP_CODE);
    $meta_error    = curl_error($chMeta);
    curl_close($chMeta);

    $responseData["meta_http"]     = $meta_http;
    $responseData["meta_response"] = json_decode($meta_response, true);
    $responseData["meta_error"]    = $meta_error;
}

// ================= RETORNO FINAL =================
echo json_encode([
    "statusCode" => 200,
    "data" => $responseData
]);
exit;
?>
