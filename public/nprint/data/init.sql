-- Script para crear la tabla templates en la base de datos inclouddb.sqlite
CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    caracteres INTEGER NOT NULL,
    paperWidthPx INTEGER NOT NULL,
    template_json TEXT NOT NULL,
    example_json TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME
);
