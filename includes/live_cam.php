<?php
/**
 * Helpers para Live con Cámara (MediaRecorder → chunks → MediaSource).
 */

function initLiveCamTables(PDO $pdo): void
{
    // Tabla de sesiones de cámara
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_cam_sessions (
            id          TEXT    PRIMARY KEY,
            seller_id   INTEGER NOT NULL,
            chunk_count INTEGER NOT NULL DEFAULT 0,
            status      TEXT    NOT NULL DEFAULT 'active'
                            CHECK(status IN ('active','ended')),
            started_at  TEXT    DEFAULT (datetime('now')),
            ended_at    TEXT,
            FOREIGN KEY(seller_id) REFERENCES users(id)
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lcs_seller ON live_cam_sessions(seller_id)");

    // Columnas extra en users (si no existen)
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $have = array_column($cols, 'name');
    if (!in_array('live_type',       $have)) $pdo->exec("ALTER TABLE users ADD COLUMN live_type TEXT DEFAULT 'link'");
    if (!in_array('live_session_id', $have)) $pdo->exec("ALTER TABLE users ADD COLUMN live_session_id TEXT");
}

/** Devuelve la ruta del directorio de chunks de una sesión. */
function liveCamDir(string $sessionId): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId);
    return __DIR__ . '/../storage/live-chunks/' . $safe;
}

/** Borra sesiones y archivos de hace más de 2 horas. */
function cleanupOldCamSessions(PDO $pdo, int $sellerId): void
{
    $stmt = $pdo->prepare(
        "SELECT id FROM live_cam_sessions WHERE seller_id=? AND started_at < datetime('now','-2 hours')"
    );
    $stmt->execute([$sellerId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        $dir = liveCamDir($sid);
        if (is_dir($dir)) {
            foreach (glob("$dir/*.webm") as $f) @unlink($f);
            @rmdir($dir);
        }
    }
    $pdo->prepare(
        "DELETE FROM live_cam_sessions WHERE seller_id=? AND started_at < datetime('now','-2 hours')"
    )->execute([$sellerId]);
}
