<?php
/**
 * Funciones de envío para emprendedoras.
 * Tabla: entrepreneur_shipping (una fila por user_id)
 */

function initShippingTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entrepreneur_shipping (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            enable_free_shipping INTEGER NOT NULL DEFAULT 0,
            enable_pickup        INTEGER NOT NULL DEFAULT 0,
            enable_express       INTEGER NOT NULL DEFAULT 0,
            free_shipping_min    INTEGER NOT NULL DEFAULT 0,
            pickup_instructions  TEXT    NOT NULL DEFAULT '',
            express_zones        TEXT    NOT NULL DEFAULT '[]',
            updated_at           TEXT    DEFAULT (datetime('now','localtime'))
        )
    ");
}

/** Devuelve la config de envío de un vendedor (array o defaults si no existe). */
function getShippingConfig(PDO $pdo, int $userId): array {
    initShippingTable($pdo);
    $stmt = $pdo->prepare("SELECT * FROM entrepreneur_shipping WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [
            'user_id'              => $userId,
            'enable_free_shipping' => 0,
            'enable_pickup'        => 0,
            'enable_express'       => 0,
            'free_shipping_min'    => 0,
            'pickup_instructions'  => '',
            'express_zones'        => [],
        ];
    }
    $row['enable_free_shipping'] = (int)$row['enable_free_shipping'];
    $row['enable_pickup']        = (int)$row['enable_pickup'];
    $row['enable_express']       = (int)$row['enable_express'];
    $row['free_shipping_min']    = (int)$row['free_shipping_min'];
    $row['express_zones']        = json_decode($row['express_zones'] ?? '[]', true) ?: [];
    return $row;
}

/** Guarda o actualiza la config de envío de un vendedor. */
function saveShippingConfig(PDO $pdo, int $userId, array $data): void {
    initShippingTable($pdo);
    $zonesJson = json_encode(array_values($data['express_zones'] ?? []), JSON_UNESCAPED_UNICODE);

    // Verificar si ya existe un registro para este usuario
    $exists = $pdo->prepare("SELECT id FROM entrepreneur_shipping WHERE user_id = ? LIMIT 1");
    $exists->execute([$userId]);

    if ($exists->fetch()) {
        // UPDATE
        $pdo->prepare("
            UPDATE entrepreneur_shipping SET
                enable_free_shipping = ?,
                enable_pickup        = ?,
                enable_express       = ?,
                free_shipping_min    = ?,
                pickup_instructions  = ?,
                express_zones        = ?,
                updated_at           = datetime('now','localtime')
            WHERE user_id = ?
        ")->execute([
            (int)($data['enable_free_shipping'] ?? 0),
            (int)($data['enable_pickup']        ?? 0),
            (int)($data['enable_express']       ?? 0),
            (int)($data['free_shipping_min']    ?? 0),
            trim($data['pickup_instructions']   ?? ''),
            $zonesJson,
            $userId,
        ]);
    } else {
        // INSERT
        $pdo->prepare("
            INSERT INTO entrepreneur_shipping
                (user_id, enable_free_shipping, enable_pickup, enable_express,
                 free_shipping_min, pickup_instructions, express_zones, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))
        ")->execute([
            $userId,
            (int)($data['enable_free_shipping'] ?? 0),
            (int)($data['enable_pickup']        ?? 0),
            (int)($data['enable_express']       ?? 0),
            (int)($data['free_shipping_min']    ?? 0),
            trim($data['pickup_instructions']   ?? ''),
            $zonesJson,
        ]);
    }
}
