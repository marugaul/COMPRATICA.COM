<?php
/**
 * Helpers para el sistema de chat en vivo de emprendedoras.
 * Solo disponible para planes de pago (price_monthly > 0).
 */

/**
 * Devuelve true si el usuario tiene una suscripción activa de pago.
 */
function hasPaidPlan(PDO $pdo, int $userId): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT s.id
            FROM entrepreneur_subscriptions s
            JOIN entrepreneur_plans p ON p.id = s.plan_id
            WHERE s.user_id = ?
              AND s.status = 'active'
              AND (s.end_date IS NULL OR s.end_date > datetime('now'))
              AND p.price_monthly > 0
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Detecta palabras inapropiadas en español e inglés.
 * Usa coincidencia de subcadena para capturar variantes.
 */
function containsProfanity(string $text): bool
{
    $bad = [
        // Español
        'puta','puto','mierda','culo','verga','pendejo','pendeja',
        'cabron','cabrona','joder','coño','chinga','chingada',
        'estupido','estupida','idiota','imbecil','gilipollas',
        'marica','maricon','perra','zorra','hijueputa','malparido',
        'malparida','gonorrea','ojete','culero','culera','pinche',
        'culiao','webon','putita','hdp','hp','desgraciado','maldito',
        // Inglés
        'fuck','shit','bitch','cunt','asshole','bastard','whore',
        'slut','faggot','retard','motherfucker','dickhead','jackass',
        'dumbass','prick','twat',
    ];
    $clean = mb_strtolower(strip_tags($text));
    foreach ($bad as $w) {
        if (strpos($clean, $w) !== false) return true;
    }
    return false;
}

/**
 * Crea las tablas de chat si no existen.
 */
function initChatTables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_chat_messages (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            seller_id   INTEGER NOT NULL,
            sender_id   INTEGER NOT NULL,
            sender_name TEXT    NOT NULL,
            sender_type TEXT    NOT NULL CHECK(sender_type IN ('client','seller')),
            message     TEXT    NOT NULL,
            is_public   INTEGER NOT NULL DEFAULT 1,
            private_to  INTEGER DEFAULT NULL,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lcm_seller  ON live_chat_messages(seller_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lcm_created ON live_chat_messages(created_at)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_chat_bans (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            seller_id      INTEGER NOT NULL,
            banned_user_id INTEGER NOT NULL,
            banned_at      TEXT    DEFAULT (datetime('now')),
            UNIQUE(seller_id, banned_user_id)
        )
    ");
}
