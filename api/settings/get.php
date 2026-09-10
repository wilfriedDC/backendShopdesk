<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "../../config/database.php";

try {

    $stmt = $pdo->query("
        SELECT id, store_name, address, created_at, updated_at
        FROM settings
        LIMIT 1
    ");

    $settings = $stmt->fetch();

    // Si aucun paramètre n'existe encore
    if (!$settings) {

        $stmt = $pdo->prepare("
            INSERT INTO settings (store_name, address)
            VALUES (?, ?)
        ");

        $stmt->execute([
            "Ma Boutique",
            "123 Rue du Commerce"
        ]);

        $settings = [
            "id" => $pdo->lastInsertId(),
            "store_name" => "Ma Boutique",
            "address" => "123 Rue du Commerce"
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $settings
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Impossible de récupérer les paramètres"
    ]);
}