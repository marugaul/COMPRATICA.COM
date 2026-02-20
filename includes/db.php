<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dbFile = __DIR__ . '/../data.sqlite';
        $init = !file_exists($dbFile);
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($init) {
            $pdo->exec("
                CREATE TABLE products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    price REAL NOT NULL DEFAULT 0,
                    stock INTEGER NOT NULL DEFAULT 0,
                    image TEXT,
                    currency TEXT NOT NULL DEFAULT 'CRC',
                    active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT,
                    updated_at TEXT
                );
            ");
            $pdo->exec("
                CREATE TABLE orders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    qty INTEGER NOT NULL DEFAULT 1,
                    buyer_email TEXT,
                    buyer_phone TEXT,
                    residency TEXT,
                    note TEXT,
                    created_at TEXT,
                    status TEXT NOT NULL DEFAULT 'Pendiente',
                    paypal_txn_id TEXT,
                    paypal_amount REAL,
                    paypal_currency TEXT,
                    proof_image TEXT,
                    exrate_used REAL,
                    FOREIGN KEY(product_id) REFERENCES products(id)
                );
            ");
            $pdo->exec("
                CREATE TABLE settings (
                    id INTEGER PRIMARY KEY CHECK (id=1),
                    exchange_rate REAL NOT NULL DEFAULT 540.00
                );
            ");
            $pdo->exec("INSERT INTO settings (id, exchange_rate) VALUES (1, 540.00)");

            // Crear tabla users unificada
            $pdo->exec("
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    phone TEXT,
                    password_hash TEXT NOT NULL,

                    -- OAuth columns (for Google/Facebook login)
                    oauth_provider TEXT,
                    oauth_id TEXT,

                    -- Company/business fields
                    company_name TEXT,
                    company_description TEXT,
                    company_logo TEXT,
                    website TEXT,

                    -- Real estate specific
                    license_number TEXT,
                    specialization TEXT,
                    bio TEXT,
                    profile_image TEXT,

                    -- Social media
                    facebook TEXT,
                    instagram TEXT,
                    whatsapp TEXT,

                    -- Affiliate specific
                    slug TEXT UNIQUE,
                    avatar TEXT,
                    fee_pct REAL DEFAULT 0.10,
                    offers_products INTEGER DEFAULT 0,
                    offers_services INTEGER DEFAULT 0,
                    business_description TEXT,

                    -- Status
                    is_active INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT (datetime('now')),
                    updated_at TEXT DEFAULT (datetime('now'))
                );
            ");

            // Crear índices
            $pdo->exec("CREATE INDEX idx_users_email ON users(email)");
            $pdo->exec("CREATE INDEX idx_users_slug ON users(slug)");
            $pdo->exec("CREATE INDEX idx_users_active ON users(is_active)");

        } else {
            $colsO = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
            $have = [];
            foreach ($colsO as $c) $have[strtolower($c['name'])]=true;
            if(empty($have['status'])) $pdo->exec("ALTER TABLE orders ADD COLUMN status TEXT NOT NULL DEFAULT 'Pendiente'");
            if(empty($have['paypal_txn_id'])) $pdo->exec("ALTER TABLE orders ADD COLUMN paypal_txn_id TEXT");
            if(empty($have['paypal_amount'])) $pdo->exec("ALTER TABLE orders ADD COLUMN paypal_amount REAL");
            if(empty($have['paypal_currency'])) $pdo->exec("ALTER TABLE orders ADD COLUMN paypal_currency TEXT");
            if(empty($have['proof_image'])) $pdo->exec("ALTER TABLE orders ADD COLUMN proof_image TEXT");
            if(empty($have['exrate_used'])) $pdo->exec("ALTER TABLE orders ADD COLUMN exrate_used REAL");

            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
            if(!in_array('settings', $tables)){
                $pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY CHECK (id=1), exchange_rate REAL NOT NULL DEFAULT 540.00)");
                $pdo->exec("INSERT INTO settings (id, exchange_rate) VALUES (1, 540.00)");
            }

            // Crear tabla users si no existe
            if(!in_array('users', $tables)){
                $pdo->exec("
                    CREATE TABLE users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        email TEXT NOT NULL UNIQUE,
                        phone TEXT,
                        password_hash TEXT NOT NULL,

                        -- OAuth columns (for Google/Facebook login)
                        oauth_provider TEXT,
                        oauth_id TEXT,

                        -- Company/business fields
                        company_name TEXT,
                        company_description TEXT,
                        company_logo TEXT,
                        website TEXT,

                        -- Real estate specific
                        license_number TEXT,
                        specialization TEXT,
                        bio TEXT,
                        profile_image TEXT,

                        -- Social media
                        facebook TEXT,
                        instagram TEXT,
                        whatsapp TEXT,

                        -- Affiliate specific
                        slug TEXT UNIQUE,
                        avatar TEXT,
                        fee_pct REAL DEFAULT 0.10,
                        offers_products INTEGER DEFAULT 0,
                        offers_services INTEGER DEFAULT 0,
                        business_description TEXT,

                        -- Status
                        is_active INTEGER DEFAULT 1,
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT DEFAULT (datetime('now'))
                    );
                ");

                // Crear índices
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_slug ON users(slug)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active)");
            } else {
                // Si la tabla existe, verificar que tenga las columnas OAuth
                $colsU = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
                $haveU = [];
                foreach ($colsU as $c) $haveU[strtolower($c['name'])]=true;

                if(empty($haveU['oauth_provider'])) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN oauth_provider TEXT");
                }
                if(empty($haveU['oauth_id'])) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN oauth_id TEXT");
                }
            }
        }
    }
    return $pdo;
}
function now_iso(){ return date('Y-m-d H:i:s'); }
function get_exchange_rate(){
    $pdo = db();
    $row = $pdo->query("SELECT exchange_rate FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    return (float)($row['exchange_rate'] ?? 540.00);
}
?>
