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

            // Crear tabla de publicaciones de empleos y servicios
            $pdo->exec("
                CREATE TABLE job_listings (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  employer_id INTEGER NOT NULL,
                  listing_type TEXT NOT NULL CHECK(listing_type IN ('job', 'service')),
                  title TEXT NOT NULL,
                  description TEXT NOT NULL,
                  category TEXT,
                  job_type TEXT CHECK(job_type IN ('full-time', 'part-time', 'freelance', 'contract', 'internship', NULL)),
                  salary_min REAL,
                  salary_max REAL,
                  salary_currency TEXT DEFAULT 'CRC',
                  salary_period TEXT CHECK(salary_period IN ('hour', 'day', 'week', 'month', 'year', 'project', NULL)),
                  service_price REAL,
                  service_price_type TEXT CHECK(service_price_type IN ('fixed', 'hourly', 'daily', 'negotiable', NULL)),
                  location TEXT,
                  province TEXT,
                  canton TEXT,
                  distrito TEXT,
                  remote_allowed INTEGER DEFAULT 0,
                  requirements TEXT,
                  benefits TEXT,
                  contact_name TEXT,
                  contact_email TEXT,
                  contact_phone TEXT,
                  contact_whatsapp TEXT,
                  application_url TEXT,
                  image_1 TEXT,
                  image_2 TEXT,
                  image_3 TEXT,
                  image_4 TEXT,
                  image_5 TEXT,
                  is_active INTEGER DEFAULT 1,
                  is_featured INTEGER DEFAULT 0,
                  start_date TEXT,
                  end_date TEXT,
                  views_count INTEGER DEFAULT 0,
                  applications_count INTEGER DEFAULT 0,
                  created_at TEXT DEFAULT (datetime('now')),
                  updated_at TEXT DEFAULT (datetime('now')),
                  FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

            // Índices para job_listings
            $pdo->exec("CREATE INDEX idx_job_listings_employer ON job_listings(employer_id)");
            $pdo->exec("CREATE INDEX idx_job_listings_type ON job_listings(listing_type)");
            $pdo->exec("CREATE INDEX idx_job_listings_category ON job_listings(category)");
            $pdo->exec("CREATE INDEX idx_job_listings_active ON job_listings(is_active)");
            $pdo->exec("CREATE INDEX idx_job_listings_province ON job_listings(province)");
            $pdo->exec("CREATE INDEX idx_job_listings_dates ON job_listings(start_date, end_date)");

            // Crear tabla de categorías
            $pdo->exec("
                CREATE TABLE job_categories (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  name TEXT NOT NULL UNIQUE,
                  icon TEXT,
                  parent_category TEXT,
                  display_order INTEGER DEFAULT 0,
                  active INTEGER DEFAULT 1
                )
            ");

            // Crear tabla de aplicaciones
            $pdo->exec("
                CREATE TABLE job_applications (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  listing_id INTEGER NOT NULL,
                  applicant_name TEXT NOT NULL,
                  applicant_email TEXT NOT NULL,
                  applicant_phone TEXT,
                  resume_url TEXT,
                  cover_letter TEXT,
                  status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'reviewed', 'interview', 'rejected', 'accepted')),
                  notes TEXT,
                  created_at TEXT DEFAULT (datetime('now')),
                  updated_at TEXT DEFAULT (datetime('now')),
                  FOREIGN KEY (listing_id) REFERENCES job_listings(id) ON DELETE CASCADE
                )
            ");

            $pdo->exec("CREATE INDEX idx_applications_listing ON job_applications(listing_id)");
            $pdo->exec("CREATE INDEX idx_applications_status ON job_applications(status)");

            // Insertar categorías por defecto
            $categories = [
                ['EMP: Tecnología e Informática', 'fa-laptop-code', 'Empleos', 1],
                ['EMP: Administración y Finanzas', 'fa-calculator', 'Empleos', 2],
                ['EMP: Ventas y Marketing', 'fa-chart-line', 'Empleos', 3],
                ['EMP: Salud y Medicina', 'fa-heartbeat', 'Empleos', 4],
                ['EMP: Educación', 'fa-graduation-cap', 'Empleos', 5],
                ['EMP: Construcción', 'fa-hard-hat', 'Empleos', 6],
                ['EMP: Hostelería y Turismo', 'fa-hotel', 'Empleos', 7],
                ['EMP: Transporte y Logística', 'fa-truck', 'Empleos', 8],
                ['EMP: Servicio al Cliente', 'fa-headset', 'Empleos', 9],
                ['EMP: Legal y Jurídico', 'fa-gavel', 'Empleos', 10],
                ['SERV: Desarrollo Web y Apps', 'fa-code', 'Servicios', 20],
                ['SERV: Diseño Gráfico', 'fa-palette', 'Servicios', 21],
                ['SERV: Marketing Digital', 'fa-bullhorn', 'Servicios', 22],
                ['SERV: Fotografía y Video', 'fa-camera', 'Servicios', 23],
                ['SERV: Consultoría', 'fa-user-tie', 'Servicios', 24],
                ['SERV: Reparaciones', 'fa-tools', 'Servicios', 25],
                ['SERV: Limpieza', 'fa-broom', 'Servicios', 26],
                ['SERV: Belleza y Estética', 'fa-cut', 'Servicios', 27],
                ['SERV: Eventos', 'fa-calendar-alt', 'Servicios', 28],
                ['SERV: Clases Particulares', 'fa-chalkboard-teacher', 'Servicios', 29],
                ['SERV: Traducción', 'fa-language', 'Servicios', 30],
                ['SERV: Legal', 'fa-balance-scale', 'Servicios', 31]
            ];

            $stmt = $pdo->prepare("INSERT INTO job_categories (name, icon, parent_category, display_order) VALUES (?, ?, ?, ?)");
            foreach ($categories as $cat) {
                $stmt->execute($cat);
            }

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

            // Crear tablas de empleos y servicios si no existen
            if(!in_array('job_listings', $tables)){
                $pdo->exec("
                    CREATE TABLE job_listings (
                      id INTEGER PRIMARY KEY AUTOINCREMENT,
                      employer_id INTEGER NOT NULL,
                      listing_type TEXT NOT NULL CHECK(listing_type IN ('job', 'service')),
                      title TEXT NOT NULL,
                      description TEXT NOT NULL,
                      category TEXT,
                      job_type TEXT CHECK(job_type IN ('full-time', 'part-time', 'freelance', 'contract', 'internship', NULL)),
                      salary_min REAL,
                      salary_max REAL,
                      salary_currency TEXT DEFAULT 'CRC',
                      salary_period TEXT CHECK(salary_period IN ('hour', 'day', 'week', 'month', 'year', 'project', NULL)),
                      service_price REAL,
                      service_price_type TEXT CHECK(service_price_type IN ('fixed', 'hourly', 'daily', 'negotiable', NULL)),
                      location TEXT,
                      province TEXT,
                      canton TEXT,
                      distrito TEXT,
                      remote_allowed INTEGER DEFAULT 0,
                      requirements TEXT,
                      benefits TEXT,
                      contact_name TEXT,
                      contact_email TEXT,
                      contact_phone TEXT,
                      contact_whatsapp TEXT,
                      application_url TEXT,
                      image_1 TEXT,
                      image_2 TEXT,
                      image_3 TEXT,
                      image_4 TEXT,
                      image_5 TEXT,
                      is_active INTEGER DEFAULT 1,
                      is_featured INTEGER DEFAULT 0,
                      start_date TEXT,
                      end_date TEXT,
                      views_count INTEGER DEFAULT 0,
                      applications_count INTEGER DEFAULT 0,
                      created_at TEXT DEFAULT (datetime('now')),
                      updated_at TEXT DEFAULT (datetime('now')),
                      FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");

                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_employer ON job_listings(employer_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_type ON job_listings(listing_type)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_category ON job_listings(category)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_active ON job_listings(is_active)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_province ON job_listings(province)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_dates ON job_listings(start_date, end_date)");
            }

            if(!in_array('job_categories', $tables)){
                $pdo->exec("
                    CREATE TABLE job_categories (
                      id INTEGER PRIMARY KEY AUTOINCREMENT,
                      name TEXT NOT NULL UNIQUE,
                      icon TEXT,
                      parent_category TEXT,
                      display_order INTEGER DEFAULT 0,
                      active INTEGER DEFAULT 1
                    )
                ");

                // Insertar categorías por defecto
                $categories = [
                    ['EMP: Tecnología e Informática', 'fa-laptop-code', 'Empleos', 1],
                    ['EMP: Administración y Finanzas', 'fa-calculator', 'Empleos', 2],
                    ['EMP: Ventas y Marketing', 'fa-chart-line', 'Empleos', 3],
                    ['EMP: Salud y Medicina', 'fa-heartbeat', 'Empleos', 4],
                    ['EMP: Educación', 'fa-graduation-cap', 'Empleos', 5],
                    ['EMP: Construcción', 'fa-hard-hat', 'Empleos', 6],
                    ['EMP: Hostelería y Turismo', 'fa-hotel', 'Empleos', 7],
                    ['EMP: Transporte y Logística', 'fa-truck', 'Empleos', 8],
                    ['EMP: Servicio al Cliente', 'fa-headset', 'Empleos', 9],
                    ['EMP: Legal y Jurídico', 'fa-gavel', 'Empleos', 10],
                    ['SERV: Desarrollo Web y Apps', 'fa-code', 'Servicios', 20],
                    ['SERV: Diseño Gráfico', 'fa-palette', 'Servicios', 21],
                    ['SERV: Marketing Digital', 'fa-bullhorn', 'Servicios', 22],
                    ['SERV: Fotografía y Video', 'fa-camera', 'Servicios', 23],
                    ['SERV: Consultoría', 'fa-user-tie', 'Servicios', 24],
                    ['SERV: Reparaciones', 'fa-tools', 'Servicios', 25],
                    ['SERV: Limpieza', 'fa-broom', 'Servicios', 26],
                    ['SERV: Belleza y Estética', 'fa-cut', 'Servicios', 27],
                    ['SERV: Eventos', 'fa-calendar-alt', 'Servicios', 28],
                    ['SERV: Clases Particulares', 'fa-chalkboard-teacher', 'Servicios', 29],
                    ['SERV: Traducción', 'fa-language', 'Servicios', 30],
                    ['SERV: Legal', 'fa-balance-scale', 'Servicios', 31]
                ];

                $stmt = $pdo->prepare("INSERT INTO job_categories (name, icon, parent_category, display_order) VALUES (?, ?, ?, ?)");
                foreach ($categories as $cat) {
                    $stmt->execute($cat);
                }
            }

            if(!in_array('job_applications', $tables)){
                $pdo->exec("
                    CREATE TABLE job_applications (
                      id INTEGER PRIMARY KEY AUTOINCREMENT,
                      listing_id INTEGER NOT NULL,
                      applicant_name TEXT NOT NULL,
                      applicant_email TEXT NOT NULL,
                      applicant_phone TEXT,
                      resume_url TEXT,
                      cover_letter TEXT,
                      status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'reviewed', 'interview', 'rejected', 'accepted')),
                      notes TEXT,
                      created_at TEXT DEFAULT (datetime('now')),
                      updated_at TEXT DEFAULT (datetime('now')),
                      FOREIGN KEY (listing_id) REFERENCES job_listings(id) ON DELETE CASCADE
                    )
                ");

                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_applications_listing ON job_applications(listing_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_applications_status ON job_applications(status)");
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
