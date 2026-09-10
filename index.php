<?php

header("Content-Type: application/json");

require_once "config/database.php";

echo json_encode([
    "message" => "Connexion à ShopDesk réussie !"
]);