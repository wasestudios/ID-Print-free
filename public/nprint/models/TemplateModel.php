<?php
namespace App\Models;
use \PDO;
// models/TemplateModel.php
class TemplateModel {
    private $pdo;


    public function __construct($dbFile = __DIR__ . '/../data/inclouddb.sqlite') {
        if (!file_exists(dirname($dbFile))) {
            mkdir(dirname($dbFile), 0777, true);
        }
        $this->pdo = new PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTemplatesTableIfNotExists();
    }

    private function createTemplatesTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            caracteres INTEGER NOT NULL,
            paperWidthPx INTEGER NOT NULL,
            template_json TEXT NOT NULL,
            example_json TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME
        )";
        $this->pdo->exec($sql);
    }

    public function insertTemplate($name, $caracteres, $paperWidthPx, $templateJson, $exampleJson) {
        $stmt = $this->pdo->prepare("INSERT INTO templates (name, caracteres, paperWidthPx, template_json, example_json, created_at) VALUES (:name, :caracteres, :paperWidthPx, :template_json, :example_json, datetime('now'))");
        $stmt->execute([
            'name' => $name,
            'caracteres' => $caracteres,
            'paperWidthPx' => $paperWidthPx,
            'template_json' => $templateJson,
            'example_json' => $exampleJson
        ]);
        return $this->pdo->lastInsertId();
    }

    public function getTemplates() {
        $stmt = $this->pdo->query("SELECT * FROM templates ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (is_array($row['template_json'])) {
                $row['template_json'] = json_encode($row['template_json'], JSON_UNESCAPED_UNICODE);
            }
            if (is_array($row['example_json'])) {
                $row['example_json'] = json_encode($row['example_json'], JSON_UNESCAPED_UNICODE);
            }
        }
        return $rows;
    }

    public function getTemplateById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM templates WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (is_array($row['template_json'])) {
                $row['template_json'] = json_encode($row['template_json'], JSON_UNESCAPED_UNICODE);
            }
            if (is_array($row['example_json'])) {
                $row['example_json'] = json_encode($row['example_json'], JSON_UNESCAPED_UNICODE);
            }
        }
        return $row;
    }

    public function updateTemplate($id, $name, $caracteres,$paperWidthPx ,$templateJson, $exampleJson) {
        $stmt = $this->pdo->prepare("UPDATE templates SET name = :name, caracteres = :caracteres, paperWidthPx = :paperWidthPx, template_json = :template_json, example_json = :example_json, updated_at = datetime('now') WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'name' => $name,
            'caracteres' => $caracteres,
            'paperWidthPx' => $paperWidthPx,
            'template_json' => $templateJson,
            'example_json' => $exampleJson
        ]);
    }

    public function deleteTemplate($id) {
        $stmt = $this->pdo->prepare("DELETE FROM templates WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
