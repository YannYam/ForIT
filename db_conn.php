<?php
require_once __DIR__ . "/config.php";

$dsn = "mysql:host=" . HOSTNAME . ";dbname=" . DB_NAME;

$options = [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    define("DBH", new PDO($dsn, DB_USER, DB_PASS, $options));
} catch (PDOException $err) {
    echo "Terdapat masalah saat menghubungkan ke database<br>";
    echo "Error: " . $err->getMessage();

    die();
}