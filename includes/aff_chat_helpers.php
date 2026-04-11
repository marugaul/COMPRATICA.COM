<?php
/**
 * includes/aff_chat_helpers.php
 * Funciones auxiliares para el chat de Venta de Garaje (por espacio/sale).
 * Las tablas están separadas del chat de emprendedoras para evitar colisiones de IDs.
 */

function initAffChatTables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS aff_chat_messages (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id     INTEGER NOT NULL,
            aff_id      INTEGER NOT NULL DEFAULT 0,
            sender_uid  INTEGER NOT NULL DEFAULT 0,
            sender_name TEXT    NOT NULL,
            sender_type TEXT    NOT NULL CHECK(sender_type IN ('client','affiliate')),
            message     TEXT    NOT NULL,
            is_public   INTEGER NOT NULL DEFAULT 1,
            private_to  INTEGER DEFAULT NULL,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_acm_sale    ON aff_chat_messages(sale_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_acm_created ON aff_chat_messages(created_at)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS aff_chat_bans (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id        INTEGER NOT NULL,
            banned_user_id INTEGER NOT NULL,
            banned_at      TEXT    DEFAULT (datetime('now')),
            UNIQUE(sale_id, banned_user_id)
        )
    ");

    // Agregar columna chat_active a sales si no existe
    try {
        $pdo->exec("ALTER TABLE sales ADD COLUMN chat_active INTEGER NOT NULL DEFAULT 0");
    } catch (Throwable $_e) {
        // La columna ya existe — ignorar
    }
}
