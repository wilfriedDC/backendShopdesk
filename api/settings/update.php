<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../../config/database.php";

// Gestion de la requête OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

// Vérifier la méthode
if ($_SERVER["REQUEST_METHOD"] !== "PUT") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Méthode non autorisée"
    ]);

    exit;
}

// Récupérer les données JSON envoyées par React
$data = json_decode(file_get_contents("php://input"), true);

$storeName = trim($data["storeName"] ?? "");
$address = trim($data["address"] ?? "");

// Validation
if ($storeName === "" || $address === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Le nom de la boutique et l'adresse sont obligatoires"
    ]);

    exit;
}

try {

    // Vérifier si les paramètres existent
    $stmt = $pdo->query("
        SELECT id
        FROM settings
        LIMIT 1
    ");

    $settings = $stmt->fetch();

    if ($settings) {

        // Mise à jour
        $stmt = $pdo->prepare("
            UPDATE settings
            SET store_name = ?,
                address = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $storeName,
            $address,
            $settings["id"]
        ]);

    } else {

        // Création si aucune ligne n'existe
        $stmt = $pdo->prepare("
            INSERT INTO settings (store_name, address)
            VALUES (?, ?)
        ");

        $stmt->execute([
            $storeName,
            $address
        ]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Paramètres enregistrés"
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Erreur lors de l'enregistrement des paramètres"
    ]);
}