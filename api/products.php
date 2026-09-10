<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../config/database.php";

$method = $_SERVER["REQUEST_METHOD"];


/*
|--------------------------------------------------------------------------
| GET : RÉCUPÉRER LES PRODUITS
|--------------------------------------------------------------------------
*/

if ($method === "GET") {

    try {

        $sql = "
            SELECT
                id,
                name,
                category,
                price,
                stock,
                min_stock AS minStock
            FROM products
            ORDER BY id DESC
        ";

        $stmt = $pdo->query($sql);

        $products = $stmt->fetchAll();

        echo json_encode([
            "success" => true,
            "data" => $products
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Erreur lors de la récupération des produits"
        ]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| POST : AJOUTER UN PRODUIT
|--------------------------------------------------------------------------
*/

if ($method === "POST") {

    try {

        $data = json_decode(file_get_contents("php://input"), true);

        if (
            empty($data["name"]) ||
            empty($data["category"]) ||
            !isset($data["price"]) ||
            !isset($data["stock"])
        ) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Informations du produit incomplètes"
            ]);

            exit;
        }

        $sql = "
            INSERT INTO products (
                name,
                category,
                price,
                stock,
                min_stock
            )
            VALUES (
                :name,
                :category,
                :price,
                :stock,
                :min_stock
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":name" => $data["name"],
            ":category" => $data["category"],
            ":price" => $data["price"],
            ":stock" => $data["stock"],
            ":min_stock" => $data["minStock"] ?? 5
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Produit ajouté avec succès",
            "id" => $pdo->lastInsertId()
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Erreur lors de l'ajout du produit"
        ]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| PUT : MODIFIER UN PRODUIT
|--------------------------------------------------------------------------
*/

if ($method === "PUT") {

    try {

        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!isset($data["id"])) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "ID du produit manquant"
            ]);

            exit;
        }

        $sql = "
            UPDATE products
            SET
                name = :name,
                category = :category,
                price = :price,
                stock = :stock,
                min_stock = :min_stock
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":id" => $data["id"],
            ":name" => $data["name"],
            ":category" => $data["category"],
            ":price" => $data["price"],
            ":stock" => $data["stock"],
            ":min_stock" => $data["minStock"]
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Produit modifié avec succès"
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Erreur lors de la modification"
        ]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE : SUPPRIMER UN PRODUIT
|--------------------------------------------------------------------------
*/

if ($method === "DELETE") {

    try {

        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!isset($data["id"])) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "ID du produit manquant"
            ]);

            exit;
        }

        $sql = "
            DELETE FROM products
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":id" => $data["id"]
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Produit supprimé avec succès"
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Erreur lors de la suppression"
        ]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| MÉTHODE NON AUTORISÉE
|--------------------------------------------------------------------------
*/

http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Méthode non autorisée"
]);