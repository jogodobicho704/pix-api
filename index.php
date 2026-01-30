<?php
header("Content-Type: application/json");

// ================= CONFIG =================
$PLUMIFY_TOKEN = getenv("PLUMIFY_TOKEN"); // se quiser validar segurança
$META_TOKEN    = "EAARFzDZBZCLZBgBQjzjnKheWdamhmwPCo1CNueZAULNegkQbXEDYK1lJYHctFxJO7NiN5zVB52lAeghqBYgtXI7TDqVvp7P9l2S6wiLsAY27ZC1FkoubuFZCVKj2dEZCWeC9y8JyCFqO6Qgcl8N5NwjKbGaaJSOs8F0ooXyz6y53kZAiBWizvCA6nezbnPjf5AZDZD";
$PIXEL_ID     = "1585550522686553";
$META_API_URL = "https://graph.facebook.com/v17.0/$PIXEL_ID/events";

// ================= RECEBENDO WEBHOOK =================
$input = file_get_contents("php://input");
$payload = json_decode($input, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(["error" => "Payload inválido"]);
    exit;
}

// ================= VALIDAÇÃO =================
// Verifica se status é paid
$status = $payload["data"]["payment_status"] ?? null;
if ($status !== "paid") {
    // Retorna 200 mas não dispara CAPI
    http_response_code(200);
    echo json_encode(["message" => "Pagamento não confirmado, nada a fazer"]);
    exit;
}

// ================= EXTRAÇÃO DOS DADOS =================
$transaction_id   = $payload["data"]["transaction_id"] ?? null;
$amount_cents     = $payload["data"]["amount"] ?? 0;
$amount           = $amount_cents / 100;
$currency         = "BRL";

$pix_copia_e_cola = $payload["data"]["pix_copia_e_cola"] ?? null;
$pix_qr_code      = $payload["data"]["pix_qr_code"] ?? null;

// Dados do cliente
$customer = $payload["data"]["customer"] ?? [];
$user_data = [
    "em" => hash("sha256", $customer["email"] ?? ""),
    "ph" => hash("sha256", $customer["phone_number"] ?? ""),
    "fn" => hash("sha256", $customer["name"] ?? ""),
    "external_id" => $customer["id"] ?? ""
];

// UTMs
$tracking = $payload["data"]["tracking"] ?? [];
$utm_source   = $tracking["utm_source"] ?? null;
$utm_campaign = $tracking["utm_campaign"] ?? null;
$utm_medium   = $tracking["utm_medium"] ?? null;
$utm_term     = $tracking["utm_term"] ?? null;
$utm_content  = $tracking["utm_content"] ?? null;

// ================= PAYLOAD META CAPI =================
$event_payload = [
    "data" => [
        [
            "event_name" => "Purchase",
            "event_time" => time(),
            "event_id"   => $transaction_id,
            "user_data"  => $user_data,
            "custom_data" => [
                "currency" => $currency,
                "value" => $amount,
                "pix_copia_e_cola" => $pix_copia_e_cola,
                "pix_qr_code" => $pix_qr_code,
                "utm_source" => $utm_source,
                "utm_campaign" => $utm_campaign,
                "utm_medium" => $utm_medium,
                "utm_term" => $utm_term,
                "utm_content" => $utm_content
            ],
            "action_source" => "website"
        ]
    ]
];

// ================= DISPARO PARA META CAPI =================
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $META_API_URL . "?access_token=" . $META_TOKEN,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($event_payload)
]);

$meta_response = curl_exec($ch);
$meta_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$meta_error = curl_error($ch);
curl_close($ch);

// ================= RESPOSTA =================
http_response_code(200);
echo json_encode([
    "plumify_status" => "ok",
    "meta_capi_http" => $meta_http,
    "meta_capi_response" => $meta_response,
    "meta_curl_error" => $meta_error
]);
exit;
