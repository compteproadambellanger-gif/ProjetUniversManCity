<?php
// db.php : fichier de connexion à la base de données

$host   = getenv('MYSQL_HOST')     ?: 'localhost';
$dbname = getenv('MYSQL_DATABASE') ?: 'mancity_univers_stats';
$user   = getenv('MYSQL_USER')     ?: 'root';
$pass   = getenv('MYSQL_PASSWORD') ?: '';
$port   = getenv('MYSQL_PORT')     ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
