<?php
header("Content-Type: application/json");

// ================= CONFIGURAÇÃO =================
$PLUMIFY_TOKEN = getenv("PLUMIFY_TOKEN"); // Token Plumify
$META_PIXEL_ID = "1585550522686553";      // Pixel Meta
$META_TOKEN    = "EAARFzDZBZCLZBgBQjzjnKheWdamhmwPCo1CNueZAULNegkQbXEDYK1lJYHctFxJO7NiN5zVB52lAeghqBYgtXI7TDqVvp7P9l2S6wiLsAY27ZC1FkoubuFZCVKj2dEZCWeC9y8JyCFqO6Qgcl8N5NwjKbGaaJSOs8F0ooXyz6y53kZAiBWizvCA6nezbnPjf5AZDZD";

// ================= RECEBENDO PAYLOAD =================
$input = file_get_contents("php://input");
$payload = json_decode($input, true);

// fallback caso venha via form-urlencoded
if (!$payload && !empty($_POST)) {
    $payload = $_POST;
}

if (!$payload) {
    http_response_code(400);
    echo json_encode(["error" => "Payload inválido"]);
    exit;
}

// ================= EXTRAÇÃO DOS DADOS =================
$result = $payload; // Plumify envia dados da transação

$transaction_id   = $result["id"] ?? null;
$payment_status   = $result["payment_status"] ?? null;
$amount           = $result["amount"] ?? 0;
$pix_copia_e_cola = $result["pix"]["pix_qr_code"] ?? null;
$pix_qr_code      = $result["pix"]["pix_url"] ?? null;
$pix_base64       = $result["pix"]["qr_code_base64"] ?? null;

// UTM
$tracking = $result["tracking"] ?? [
    "utm_source"   => null,
    "utm_campaign" => null,
    "utm_medium"   => null,
    "utm_term"     => null,
    "utm_content"  => null
];

// Dados do cliente
$customer = $result["customer"] ?? [
    "name"         => null,
    "email"        => null,
    "phone_number" => null,
    "document"     => null
];

// ================= ENVIO META CAPI =================
$user_data = [
    "em"  => hash('sha256', strtolower($customer["email"] ?? "")),
    "ph"  => hash('sha256', preg_replace("/\D/", "", $customer["phone_number"] ?? "")),
];

$meta_payload = [
    "data" => [
        [
            "event_name" => "Purchase",
            "event_time" => time(),
            "event_id"   => $transaction_id,
            "user_data"  => $user_data,
            "custom_data" => [
                "currency"          => "BRL",
                "value"             => $amount / 100,
                "pix_copia_e_cola"  => $pix_copia_e_cola,
                "pix_qr_code"       => $pix_qr_code,
                "utm_source"        => $tracking['utm_source'] ?? null,
                "utm_campaign"      => $tracking['utm_campaign'] ?? null,
                "utm_medium"        => $tracking['utm_medium'] ?? null,
                "utm_content"       => $tracking['utm_content'] ?? null,
                "utm_term"          => $tracking['utm_term'] ?? null
            ],
            "action_source" => "website"
        ]
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "https://graph.facebook.com/v17.0/$META_PIXEL_ID/events?access_token=$META_TOKEN",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS     => json_encode($meta_payload)
]);

$meta_response = curl_exec($ch);
$meta_http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$meta_error    = curl_error($ch);
curl_close($ch);

// ================= RESPOSTA PARA O SENDBOT =================
echo json_encode([
    "statusCode"        => 200,
    "data" => [
        "transaction_id"   => $transaction_id,
        "payment_status"   => $payment_status,
        "pix_copia_e_cola" => $pix_copia_e_cola,
        "pix_qr_code"      => $pix_qr_code,
        "pix_base64"       => $pix_base64,
        "meta_capi_http"   => $meta_http,
        "meta_capi_response" => $meta_response,
        "meta_curl_error"  => $meta_error
    ]
]);
exit;
