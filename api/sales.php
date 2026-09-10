<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| GESTION OPTIONS / CORS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}


/*
|--------------------------------------------------------------------------
| CONNEXION BASE DE DONNÉES
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


$method = $_SERVER["REQUEST_METHOD"];


/*
|--------------------------------------------------------------------------
| GET : RÉCUPÉRER LES VENTES
|--------------------------------------------------------------------------
*/

if ($method === "GET") {

    try {

        /*
        |------------------------------------------------------------------
        | RÉCUPÉRER LES VENTES
        |------------------------------------------------------------------
        */

        $sql = "
            SELECT
                id,
                subtotal,
                tax,
                total,
                payment_method,
                status,
                created_at
            FROM sales
            ORDER BY created_at DESC
        ";

        $stmt = $pdo->query($sql);

        $sales = $stmt->fetchAll();


        /*
        |------------------------------------------------------------------
        | RÉCUPÉRER LES ARTICLES DE CHAQUE VENTE
        |------------------------------------------------------------------
        */

        foreach ($sales as &$sale) {

            $itemsSql = "
                SELECT
                    id,
                    product_id,
                    product_name,
                    price,
                    quantity,
                    total
                FROM sale_items
                WHERE sale_id = :sale_id
            ";

            $itemsStmt = $pdo->prepare($itemsSql);

            $itemsStmt->execute([
                ":sale_id" => $sale["id"]
            ]);

            $items = $itemsStmt->fetchAll();


            /*
            |--------------------------------------------------------------
            | FORMAT POUR REACT
            |--------------------------------------------------------------
            */

            $sale["items"] = array_map(
                function ($item) {

                    return [
                        "id" => (int) $item["id"],

                        "produitId" =>
                            (int) $item["product_id"],

                        "nom" =>
                            $item["product_name"],

                        "prix" =>
                            (float) $item["price"],

                        "quantite" =>
                            (int) $item["quantity"],

                        "total" =>
                            (float) $item["total"]
                    ];

                },
                $items
            );


            /*
            |--------------------------------------------------------------
            | CONVERSION DES TYPES
            |--------------------------------------------------------------
            */

            $sale["id"] =
                (int) $sale["id"];

            $sale["subtotal"] =
                (float) $sale["subtotal"];

            $sale["tax"] =
                (float) $sale["tax"];

            $sale["total"] =
                (float) $sale["total"];

        }


        echo json_encode([
            "success" => true,
            "data" => $sales
        ]);

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" =>
                "Erreur lors de la récupération des ventes",

            "error" =>
                $e->getMessage()
        ]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| POST : CRÉER UNE VENTE
|--------------------------------------------------------------------------
*/

if ($method === "POST") {

    try {

        /*
        |------------------------------------------------------------------
        | RÉCUPÉRER LES DONNÉES JSON
        |------------------------------------------------------------------
        */

        $input = file_get_contents("php://input");

        $data = json_decode(
            $input,
            true
        );


        /*
        |------------------------------------------------------------------
        | VÉRIFICATION DES DONNÉES
        |------------------------------------------------------------------
        */

        if (
            !$data ||
            !isset($data["items"]) ||
            !is_array($data["items"]) ||
            count($data["items"]) === 0
        ) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" =>
                    "Le panier est vide ou invalide"
            ]);

            exit;
        }


        /*
        |------------------------------------------------------------------
        | MÉTHODE DE PAIEMENT
        |------------------------------------------------------------------
        */

        $paymentMethod =
            $data["paymentMethod"]
            ?? "especes";


        /*
        |------------------------------------------------------------------
        | VALEURS AUTORISÉES
        |------------------------------------------------------------------
        */

        $allowedPaymentMethods = [
            "especes",
            "carte",
            "qr"
        ];


        if (
            !in_array(
                $paymentMethod,
                $allowedPaymentMethods
            )
        ) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" =>
                    "Méthode de paiement invalide"
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | CALCUL DES TOTAUX
        |--------------------------------------------------------------------------
        |
        | Important :
        | On ne fait PAS confiance au total envoyé par React.
        | Le backend recalcule tout.
        |
        */

        $subtotal = 0;


        foreach ($data["items"] as $item) {

            if (
                !isset($item["produitId"]) ||
                !isset($item["quantite"])
            ) {

                throw new Exception(
                    "Données d'article invalides"
                );
            }


            $quantity =
                (int) $item["quantite"];


            if ($quantity <= 0) {

                throw new Exception(
                    "Quantité invalide"
                );

            }


            /*
            |----------------------------------------------------------------
            | RÉCUPÉRER LE PRODUIT DEPUIS LA BASE
            |----------------------------------------------------------------
            */

            $productSql = "
                SELECT
                    id,
                    name,
                    price,
                    stock
                FROM products
                WHERE id = :id
            ";


            $productStmt =
                $pdo->prepare(
                    $productSql
                );


            $productStmt->execute([
                ":id" =>
                    $item["produitId"]
            ]);


            $product =
                $productStmt->fetch();


            /*
            |----------------------------------------------------------------
            | PRODUIT INTROUVABLE
            |----------------------------------------------------------------
            */

            if (!$product) {

                throw new Exception(
                    "Produit introuvable : "
                    . $item["produitId"]
                );

            }


            /*
            |----------------------------------------------------------------
            | VÉRIFIER LE STOCK
            |----------------------------------------------------------------
            */

            if (
                $quantity >
                (int) $product["stock"]
            ) {

                throw new Exception(
                    "Stock insuffisant pour : "
                    . $product["name"]
                );

            }


            /*
            |----------------------------------------------------------------
            | CALCUL TOTAL ARTICLE
            |----------------------------------------------------------------
            */

            $price =
                (float) $product["price"];


            $itemTotal =
                $price *
                $quantity;


            $subtotal +=
                $itemTotal;

        }


        /*
        |--------------------------------------------------------------------------
        | CALCUL TVA
        |--------------------------------------------------------------------------
        */

        $tax =
            $subtotal * 0.20;


        /*
        |--------------------------------------------------------------------------
        | TOTAL TTC
        |--------------------------------------------------------------------------
        */

        $total =
            $subtotal + $tax;


        /*
        |--------------------------------------------------------------------------
        | DÉMARRER LA TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | CRÉER LA VENTE
        |--------------------------------------------------------------------------
        */

        $saleSql = "
            INSERT INTO sales (
                subtotal,
                tax,
                total,
                payment_method,
                status
            )
            VALUES (
                :subtotal,
                :tax,
                :total,
                :payment_method,
                :status
            )
        ";


        $saleStmt =
            $pdo->prepare(
                $saleSql
            );


        $saleStmt->execute([

            ":subtotal" =>
                $subtotal,

            ":tax" =>
                $tax,

            ":total" =>
                $total,

            ":payment_method" =>
                $paymentMethod,

            ":status" =>
                "payée"

        ]);


        /*
        |--------------------------------------------------------------------------
        | ID DE LA VENTE
        |--------------------------------------------------------------------------
        */

        $saleId =
            $pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | ENREGISTRER LES ARTICLES
        |--------------------------------------------------------------------------
        */

        foreach ($data["items"] as $item) {


            /*
            |----------------------------------------------------------------
            | RÉCUPÉRER LE PRODUIT
            |----------------------------------------------------------------
            */

            $productSql = "
                SELECT
                    id,
                    name,
                    price,
                    stock
                FROM products
                WHERE id = :id
            ";


            $productStmt =
                $pdo->prepare(
                    $productSql
                );


            $productStmt->execute([

                ":id" =>
                    $item["produitId"]

            ]);


            $product =
                $productStmt->fetch();


            $quantity =
                (int) $item["quantite"];


            $price =
                (float) $product["price"];


            $itemTotal =
                $price *
                $quantity;


            /*
            |----------------------------------------------------------------
            | AJOUTER SALE ITEM
            |----------------------------------------------------------------
            */

            $itemSql = "
                INSERT INTO sale_items (

                    sale_id,

                    product_id,

                    product_name,

                    price,

                    quantity,

                    total

                )

                VALUES (

                    :sale_id,

                    :product_id,

                    :product_name,

                    :price,

                    :quantity,

                    :total

                )
            ";


            $itemStmt =
                $pdo->prepare(
                    $itemSql
                );


            $itemStmt->execute([

                ":sale_id" =>
                    $saleId,

                ":product_id" =>
                    $product["id"],

                ":product_name" =>
                    $product["name"],

                ":price" =>
                    $price,

                ":quantity" =>
                    $quantity,

                ":total" =>
                    $itemTotal

            ]);


            /*
            |----------------------------------------------------------------
            | METTRE À JOUR LE STOCK
            |----------------------------------------------------------------
            */

            $stockSql = "
                UPDATE products

                SET stock =
                    stock - :quantity

                WHERE id = :id
            ";


            $stockStmt =
                $pdo->prepare(
                    $stockSql
                );


            $stockStmt->execute([

                ":quantity" =>
                    $quantity,

                ":id" =>
                    $product["id"]

            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDER LA TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | RÉPONSE
        |--------------------------------------------------------------------------
        */

        echo json_encode([

            "success" =>
                true,

            "message" =>
                "Vente enregistrée avec succès",

            "sale" => [

                "id" =>
                    (int) $saleId,

                "subtotal" =>
                    $subtotal,

                "tax" =>
                    $tax,

                "total" =>
                    $total,

                "paymentMethod" =>
                    $paymentMethod,

                "status" =>
                    "payée"

            ]

        ]);


    } catch (Exception $e) {


        /*
        |--------------------------------------------------------------------------
        | ANNULER LA TRANSACTION EN CAS D'ERREUR
        |--------------------------------------------------------------------------
        */

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();

        }


        http_response_code(500);


        echo json_encode([

            "success" =>
                false,

            "message" =>
                "Erreur lors de la vente",

            "error" =>
                $e->getMessage()

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

    "success" =>
        false,

    "message" =>
        "Méthode non autorisée"

]);