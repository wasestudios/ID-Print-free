<?php
namespace App\Models;
use \PDO;
// models/SqliteModel.php
class SqliteModel {
    private $pdo;

    public function __construct($dbFile = __DIR__ . '/../data/database.sqlite') {
        if (!file_exists(dirname($dbFile))) {
            mkdir(dirname($dbFile), 0777, true);
        }
        $this->pdo = new PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Ejemplo: crear tabla si no existe
    public function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            data TEXT NOT NULL
        )";
        $this->pdo->exec($sql);
    }

    // Ejemplo: insertar un ticket
    public function insertTicket($data) {
        $stmt = $this->pdo->prepare("INSERT INTO tickets (data) VALUES (:data)");
        $stmt->execute(['data' => $data]);
        return $this->pdo->lastInsertId();
    }

    // Ejemplo: obtener todos los tickets
    public function getTickets() {
        $stmt = $this->pdo->query("SELECT * FROM tickets ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
