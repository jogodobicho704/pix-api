if ($httpCode >= 400 || !$response) {
    http_response_code(500);
    echo json_encode([
        "erro" => "Falha ao chamar Plumify",
        "http_code" => $httpCode,
        "curl_error" => curl_error($ch),
        "response_raw" => $response
    ]);
    exit;
}
