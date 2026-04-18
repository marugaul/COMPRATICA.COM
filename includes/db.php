<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dbFile = __DIR__ . '/../data.sqlite';
        $init = !file_exists($dbFile);
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Improve concurrency and reduce transient I/O errors
        $pdo->exec("PRAGMA journal_mode = WAL");
        $pdo->exec("PRAGMA synchronous = NORMAL");
        $pdo->exec("PRAGMA busy_timeout = 5000");
        $pdo->exec("PRAGMA cache_size = -4096");
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
                  pricing_plan_id INTEGER,
                  payment_status TEXT DEFAULT 'pending' CHECK(payment_status IN ('pending', 'confirmed', 'free')),
                  payment_id TEXT,
                  payment_date TEXT,
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
            $pdo->exec("CREATE INDEX idx_job_listings_pricing_plan ON job_listings(pricing_plan_id)");
            $pdo->exec("CREATE INDEX idx_job_listings_payment_status ON job_listings(payment_status)");

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

            // Crear tabla de sesiones de usuario
            $pdo->exec("
                CREATE TABLE user_sessions (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  user_id INTEGER NOT NULL,
                  session_id TEXT NOT NULL,
                  ip_address TEXT,
                  user_agent TEXT,
                  created_at TEXT DEFAULT (datetime('now')),
                  last_activity TEXT DEFAULT (datetime('now')),
                  revoked INTEGER DEFAULT 0,
                  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

            $pdo->exec("CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id)");
            $pdo->exec("CREATE INDEX idx_user_sessions_session_id ON user_sessions(session_id)");
            $pdo->exec("CREATE INDEX idx_user_sessions_revoked ON user_sessions(revoked)");

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
            } else {
                // Si la tabla existe, verificar que tenga las columnas de pago
                $colsJ = $pdo->query("PRAGMA table_info(job_listings)")->fetchAll(PDO::FETCH_ASSOC);
                $haveJ = [];
                foreach ($colsJ as $c) $haveJ[strtolower($c['name'])]=true;

                if(empty($haveJ['pricing_plan_id'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN pricing_plan_id INTEGER");
                }
                if(empty($haveJ['payment_status'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN payment_status TEXT DEFAULT 'pending' CHECK(payment_status IN ('pending', 'confirmed', 'free'))");
                }
                if(empty($haveJ['payment_id'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN payment_id TEXT");
                }
                if(empty($haveJ['payment_date'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN payment_date TEXT");
                }
                if(empty($haveJ['payment_rejected'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN payment_rejected INTEGER DEFAULT 0");
                }

                // Crear índices si no existen
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_pricing_plan ON job_listings(pricing_plan_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_payment_status ON job_listings(payment_status)");

                // Columnas para importación automática
                if(empty($haveJ['import_source'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN import_source TEXT DEFAULT NULL");
                }
                if(empty($haveJ['source_url'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN source_url TEXT DEFAULT NULL");
                }
                if(empty($haveJ['flyer_image'])) {
                    $pdo->exec("ALTER TABLE job_listings ADD COLUMN flyer_image TEXT DEFAULT NULL");
                }
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

            // Crear tabla de sesiones de usuario si no existe
            if(!in_array('user_sessions', $tables)){
                $pdo->exec("
                    CREATE TABLE user_sessions (
                      id INTEGER PRIMARY KEY AUTOINCREMENT,
                      user_id INTEGER NOT NULL,
                      session_id TEXT NOT NULL,
                      ip_address TEXT,
                      user_agent TEXT,
                      created_at TEXT DEFAULT (datetime('now')),
                      last_activity TEXT DEFAULT (datetime('now')),
                      revoked INTEGER DEFAULT 0,
                      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");

                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_sessions_user_id ON user_sessions(user_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_sessions_session_id ON user_sessions(session_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_sessions_revoked ON user_sessions(revoked)");
            }

            // ============================================================
            // TABLAS DE EMPRENDEDORAS
            // ============================================================
            if(!in_array('entrepreneur_plans', $tables)){
                $pdo->exec("
                    CREATE TABLE entrepreneur_plans (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        description TEXT,
                        price_monthly REAL NOT NULL DEFAULT 0,
                        price_annual REAL NOT NULL DEFAULT 0,
                        max_products INTEGER NOT NULL DEFAULT 5,
                        commission_rate REAL NOT NULL DEFAULT 0,
                        features TEXT,
                        is_active INTEGER NOT NULL DEFAULT 1,
                        display_order INTEGER NOT NULL DEFAULT 0,
                        created_at TEXT DEFAULT (datetime('now'))
                    )
                ");

                // Plan gratuito por defecto
                $pdo->exec("
                    INSERT INTO entrepreneur_plans (name, description, price_monthly, price_annual, max_products, commission_rate, features, is_active, display_order)
                    VALUES (
                        'Plan Gratuito',
                        'Ideal para comenzar a vender en CompraTica',
                        0, 0, 5, 0,
                        '[\"Hasta 5 productos\",\"Perfil de emprendedora\",\"Soporte básico\"]',
                        1, 1
                    )
                ");
                $pdo->exec("
                    INSERT INTO entrepreneur_plans (name, description, price_monthly, price_annual, max_products, commission_rate, features, is_active, display_order)
                    VALUES (
                        'Plan Emprendedor/a',
                        'Para emprendedoras que quieren crecer',
                        9900, 99000, 50, 0,
                        '[\"Hasta 50 productos\",\"Estadísticas avanzadas\",\"Soporte prioritario\",\"Sin comisiones\"]',
                        1, 2
                    )
                ");
                $pdo->exec("
                    INSERT INTO entrepreneur_plans (name, description, price_monthly, price_annual, max_products, commission_rate, features, is_active, display_order)
                    VALUES (
                        'Plan Premium',
                        'Para negocios en pleno crecimiento',
                        19900, 199000, 0, 0,
                        '[\"Productos ilimitados\",\"Estadísticas avanzadas\",\"Soporte VIP\",\"Sin comisiones\",\"Destacado en catálogo\"]',
                        1, 3
                    )
                ");
            }

            if(!in_array('entrepreneur_subscriptions', $tables)){
                $pdo->exec("
                    CREATE TABLE entrepreneur_subscriptions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        plan_id INTEGER NOT NULL,
                        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','active','cancelled','expired')),
                        payment_method TEXT,
                        payment_date TEXT,
                        start_date TEXT,
                        end_date TEXT,
                        auto_renew INTEGER NOT NULL DEFAULT 0,
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT DEFAULT (datetime('now')),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (plan_id) REFERENCES entrepreneur_plans(id)
                    )
                ");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ent_subs_user ON entrepreneur_subscriptions(user_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ent_subs_status ON entrepreneur_subscriptions(status)");
            }

            if(!in_array('entrepreneur_categories', $tables)){
                $pdo->exec("
                    CREATE TABLE entrepreneur_categories (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        icon TEXT,
                        display_order INTEGER DEFAULT 0,
                        is_active INTEGER DEFAULT 1
                    )
                ");
                $cats = [
                    ['Ropa y Moda','fa-tshirt',1],['Accesorios','fa-gem',2],['Belleza y Cuidado','fa-spa',3],
                    ['Artesanías','fa-paint-brush',4],['Alimentos y Bebidas','fa-utensils',5],
                    ['Hogar y Decoración','fa-home',6],['Bebé y Niños','fa-baby',7],
                    ['Salud y Bienestar','fa-heartbeat',8],['Tecnología','fa-laptop',9],['Otros','fa-box',10]
                ];
                $s = $pdo->prepare("INSERT INTO entrepreneur_categories (name,icon,display_order) VALUES(?,?,?)");
                foreach($cats as $c) $s->execute($c);
            }

            if(!in_array('entrepreneur_products', $tables)){
                $pdo->exec("
                    CREATE TABLE entrepreneur_products (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        category_id INTEGER,
                        name TEXT NOT NULL,
                        description TEXT,
                        price REAL NOT NULL DEFAULT 0,
                        currency TEXT NOT NULL DEFAULT 'CRC',
                        stock INTEGER DEFAULT 0,
                        sku TEXT,
                        image_1 TEXT,
                        image_2 TEXT,
                        image_3 TEXT,
                        image_4 TEXT,
                        image_5 TEXT,
                        whatsapp_number TEXT,
                        weight_kg REAL,
                        size_cm_length REAL,
                        size_cm_width REAL,
                        size_cm_height REAL,
                        accepts_sinpe INTEGER DEFAULT 0,
                        accepts_paypal INTEGER DEFAULT 0,
                        accepts_card INTEGER DEFAULT 0,
                        sinpe_phone TEXT,
                        paypal_email TEXT,
                        featured INTEGER DEFAULT 0,
                        shipping_available INTEGER DEFAULT 0,
                        pickup_available INTEGER DEFAULT 0,
                        pickup_location TEXT,
                        is_active INTEGER NOT NULL DEFAULT 1,
                        views_count INTEGER NOT NULL DEFAULT 0,
                        sales_count INTEGER NOT NULL DEFAULT 0,
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT DEFAULT (datetime('now')),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (category_id) REFERENCES entrepreneur_categories(id)
                    )
                ");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ent_prod_user ON entrepreneur_products(user_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ent_prod_active ON entrepreneur_products(is_active)");
            } else {
                // Migrar columnas faltantes en tablas existentes
                $colsEP = $pdo->query("PRAGMA table_info(entrepreneur_products)")->fetchAll(PDO::FETCH_ASSOC);
                $haveEP = [];
                foreach ($colsEP as $c) $haveEP[strtolower($c['name'])] = true;

                if(empty($haveEP['sku']))               $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN sku TEXT");
                if(empty($haveEP['currency']))           $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN currency TEXT NOT NULL DEFAULT 'CRC'");
                if(empty($haveEP['image_4']))            $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN image_4 TEXT");
                if(empty($haveEP['image_5']))            $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN image_5 TEXT");
                if(empty($haveEP['weight_kg']))          $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN weight_kg REAL");
                if(empty($haveEP['size_cm_length']))     $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN size_cm_length REAL");
                if(empty($haveEP['size_cm_width']))      $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN size_cm_width REAL");
                if(empty($haveEP['size_cm_height']))     $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN size_cm_height REAL");
                if(empty($haveEP['accepts_sinpe']))      $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN accepts_sinpe INTEGER DEFAULT 0");
                if(empty($haveEP['accepts_paypal']))     $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN accepts_paypal INTEGER DEFAULT 0");
                if(empty($haveEP['accepts_card']))       $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN accepts_card INTEGER DEFAULT 0");
                if(empty($haveEP['sinpe_phone']))        $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN sinpe_phone TEXT");
                if(empty($haveEP['paypal_email']))       $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN paypal_email TEXT");
                if(empty($haveEP['featured']))           $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN featured INTEGER DEFAULT 0");
                if(empty($haveEP['shipping_available'])) $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN shipping_available INTEGER DEFAULT 0");
                if(empty($haveEP['pickup_available']))   $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN pickup_available INTEGER DEFAULT 0");
                if(empty($haveEP['pickup_location']))    $pdo->exec("ALTER TABLE entrepreneur_products ADD COLUMN pickup_location TEXT");
            }

            if(!in_array('entrepreneur_orders', $tables)){
                $pdo->exec("
                    CREATE TABLE entrepreneur_orders (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        product_id INTEGER NOT NULL,
                        seller_user_id INTEGER NOT NULL,
                        buyer_name TEXT,
                        buyer_email TEXT,
                        buyer_phone TEXT,
                        quantity INTEGER NOT NULL DEFAULT 1,
                        total_price REAL NOT NULL DEFAULT 0,
                        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','confirmed','completed','cancelled')),
                        notes TEXT,
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT DEFAULT (datetime('now')),
                        FOREIGN KEY (product_id) REFERENCES entrepreneur_products(id),
                        FOREIGN KEY (seller_user_id) REFERENCES users(id)
                    )
                ");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ent_orders_seller ON entrepreneur_orders(seller_user_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ent_orders_status ON entrepreneur_orders(status)");
            }

            // ── Tabla de identidad de emprendedoras ────────────────────────
            // Separa emprendedoras (vendedoras) de clientes finales en users.
            if(!in_array('entrepreneurs', $tables)){
                $pdo->exec("
                    CREATE TABLE entrepreneurs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL UNIQUE,
                        status TEXT NOT NULL DEFAULT 'active',
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT DEFAULT (datetime('now')),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneurs_user ON entrepreneurs(user_id)");

                // Migrar datos existentes: cualquier user_id en entrepreneur_subscriptions o entrepreneur_products
                $pdo->exec("
                    INSERT OR IGNORE INTO entrepreneurs (user_id)
                    SELECT DISTINCT user_id FROM entrepreneur_subscriptions
                ");
                $pdo->exec("
                    INSERT OR IGNORE INTO entrepreneurs (user_id)
                    SELECT DISTINCT user_id FROM entrepreneur_products
                ");
            }

            // ── Tabla de log de importaciones ──────────────────────────────
            if(!in_array('job_import_log', $tables)){
                $pdo->exec("
                    CREATE TABLE job_import_log (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        source TEXT NOT NULL,
                        started_at TEXT DEFAULT (datetime('now')),
                        finished_at TEXT,
                        inserted INTEGER DEFAULT 0,
                        skipped  INTEGER DEFAULT 0,
                        errors   INTEGER DEFAULT 0,
                        message  TEXT
                    )
                ");
            }

            // ── Tabla de afiliados (Venta de Garaje) - SEPARADA de users ───
            if(!in_array('affiliates', $tables)){
                $pdo->exec("
                    CREATE TABLE affiliates (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        email TEXT NOT NULL UNIQUE,
                        phone TEXT DEFAULT '',
                        password_hash TEXT NOT NULL,
                        oauth_provider TEXT DEFAULT NULL,
                        oauth_id TEXT DEFAULT NULL,
                        is_active INTEGER DEFAULT 1,
                        created_at TEXT DEFAULT (datetime('now')),
                        updated_at TEXT DEFAULT (datetime('now'))
                    )
                ");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_affiliates_email ON affiliates(email)");
            } else {
                // Migración silenciosa: añadir columnas oauth si no existen
                $colsAff = array_column($pdo->query("PRAGMA table_info(affiliates)")->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (!in_array('oauth_provider', $colsAff)) $pdo->exec("ALTER TABLE affiliates ADD COLUMN oauth_provider TEXT DEFAULT NULL");
                if (!in_array('oauth_id',       $colsAff)) $pdo->exec("ALTER TABLE affiliates ADD COLUMN oauth_id TEXT DEFAULT NULL");
            }

            // ── Términos y Condiciones ──────────────────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS terms_conditions (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                type        TEXT NOT NULL,
                title       TEXT NOT NULL,
                content     TEXT NOT NULL,
                version     TEXT NOT NULL DEFAULT '1.0',
                is_active   INTEGER NOT NULL DEFAULT 1,
                created_at  TEXT DEFAULT (datetime('now')),
                updated_at  TEXT DEFAULT (datetime('now'))
            )");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_terms_type ON terms_conditions(type)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS terms_acceptances (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_table  TEXT NOT NULL,
                user_id     INTEGER NOT NULL,
                terms_type  TEXT NOT NULL,
                version     TEXT NOT NULL,
                accepted_at TEXT DEFAULT (datetime('now')),
                ip_address  TEXT
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_accept_user ON terms_acceptances(user_table, user_id)");

            // ── Transacciones SwiftPay ─────────────────────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS swiftpay_transactions (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id       TEXT NOT NULL,
                mode            TEXT NOT NULL DEFAULT 'sandbox',
                type            TEXT NOT NULL,
                status          TEXT NOT NULL DEFAULT 'pending',
                amount          TEXT,
                currency        TEXT,
                description     TEXT,
                order_id        TEXT,
                rrn             TEXT,
                int_ref         TEXT,
                auth_code       TEXT,
                token_card      TEXT,
                is_3ds          INTEGER NOT NULL DEFAULT 0,
                reference_id    INTEGER DEFAULT 0,
                reference_table TEXT DEFAULT '',
                error_message   TEXT,
                raw_response    TEXT,
                ip_address      TEXT,
                created_at      TEXT DEFAULT (datetime('now')),
                updated_at      TEXT DEFAULT (datetime('now'))
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sp_client_id ON swiftpay_transactions(client_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sp_status    ON swiftpay_transactions(status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sp_order_id  ON swiftpay_transactions(order_id)");
            // Migración: raw_request y raw_response_3ds (guardado a partir de 2026-03)
            {
                $haveSP = [];
                foreach ($pdo->query("PRAGMA table_info(swiftpay_transactions)")->fetchAll(PDO::FETCH_ASSOC) as $c)
                    $haveSP[$c['name']] = true;
                if (empty($haveSP['raw_request']))
                    $pdo->exec("ALTER TABLE swiftpay_transactions ADD COLUMN raw_request TEXT");
                if (empty($haveSP['raw_response_3ds']))
                    $pdo->exec("ALTER TABLE swiftpay_transactions ADD COLUMN raw_response_3ds TEXT");
            }

            // ── Tabla métodos de pago de afiliados (Venta de Garaje) ──────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS affiliate_payment_methods (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                affiliate_id INTEGER NOT NULL,
                sinpe_phone  TEXT,
                paypal_email TEXT,
                active_sinpe INTEGER DEFAULT 0,
                active_paypal INTEGER DEFAULT 0,
                active_card  INTEGER DEFAULT 0,
                created_at   TEXT DEFAULT (datetime('now','localtime')),
                updated_at   TEXT DEFAULT (datetime('now','localtime'))
            )");
            // Migrar columna active_card si la tabla ya existía
            $colsAPM = $pdo->query("PRAGMA table_info(affiliate_payment_methods)")->fetchAll(PDO::FETCH_ASSOC);
            $haveAPM = [];
            foreach ($colsAPM as $c) $haveAPM[strtolower($c['name'])] = true;
            if (empty($haveAPM['active_card'])) {
                $pdo->exec("ALTER TABLE affiliate_payment_methods ADD COLUMN active_card INTEGER DEFAULT 0");
            }

            // ── Portales: Bienes Raíces, Servicios (tablas compartidas) ──────
            $pdo->exec("CREATE TABLE IF NOT EXISTS listing_pricing (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                name         TEXT NOT NULL,
                duration_days INTEGER NOT NULL,
                price_usd    REAL NOT NULL DEFAULT 0,
                price_crc    REAL NOT NULL DEFAULT 0,
                is_active    INTEGER DEFAULT 1,
                is_featured  INTEGER DEFAULT 0,
                description  TEXT,
                display_order INTEGER DEFAULT 0,
                created_at   TEXT DEFAULT (datetime('now')),
                updated_at   TEXT DEFAULT (datetime('now'))
            )");
            // Seed pricing plans if empty
            if ((int)$pdo->query("SELECT COUNT(*) FROM listing_pricing")->fetchColumn() === 0) {
                $pdo->exec("INSERT INTO listing_pricing (name,duration_days,price_usd,price_crc,is_active,is_featured,description,display_order) VALUES
                    ('Gratis 7 días',7,0.00,0.00,1,0,'Prueba gratis por 7 días',1),
                    ('Plan 30 días',30,1.00,540.00,1,1,'Publicación por 30 días',2),
                    ('Plan 90 días',90,2.00,1080.00,1,1,'Publicación por 90 días - Ahorrá 33%',3)");
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS real_estate_agents (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                name         TEXT NOT NULL,
                email        TEXT NOT NULL UNIQUE,
                phone        TEXT,
                password_hash TEXT NOT NULL,
                company_name TEXT,
                company_description TEXT,
                company_logo TEXT,
                website      TEXT,
                license_number TEXT,
                specialization TEXT,
                bio          TEXT,
                profile_image TEXT,
                facebook     TEXT,
                instagram    TEXT,
                whatsapp     TEXT,
                slug         TEXT,
                is_active    INTEGER DEFAULT 1,
                created_at   TEXT DEFAULT (datetime('now')),
                updated_at   TEXT DEFAULT (datetime('now'))
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS real_estate_listings (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER,
                agent_id     INTEGER,
                category_id  INTEGER NOT NULL,
                title        TEXT NOT NULL,
                description  TEXT,
                price        REAL NOT NULL DEFAULT 0,
                currency     TEXT DEFAULT 'CRC',
                location     TEXT,
                province     TEXT,
                canton       TEXT,
                district     TEXT,
                bedrooms     INTEGER DEFAULT 0,
                bathrooms    INTEGER DEFAULT 0,
                area_m2      REAL DEFAULT 0,
                parking_spaces INTEGER DEFAULT 0,
                features     TEXT,
                images       TEXT,
                contact_name  TEXT,
                contact_phone TEXT,
                contact_email TEXT,
                contact_whatsapp TEXT,
                listing_type  TEXT DEFAULT 'sale',
                latitude     REAL DEFAULT NULL,
                longitude    REAL DEFAULT NULL,
                pricing_plan_id INTEGER NOT NULL DEFAULT 1,
                is_active    INTEGER DEFAULT 1,
                is_featured  INTEGER DEFAULT 0,
                views_count  INTEGER DEFAULT 0,
                start_date   TEXT,
                end_date     TEXT,
                payment_status TEXT DEFAULT 'pending',
                payment_id   TEXT,
                created_at   TEXT DEFAULT (datetime('now')),
                updated_at   TEXT DEFAULT (datetime('now'))
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rel_agent    ON real_estate_listings(agent_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rel_active   ON real_estate_listings(is_active)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rel_province ON real_estate_listings(province)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS service_listings (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                agent_id     INTEGER NOT NULL,
                category_id  INTEGER NOT NULL,
                title        TEXT NOT NULL,
                description  TEXT,
                service_type TEXT DEFAULT 'presencial',
                price_from   REAL DEFAULT 0,
                price_to     REAL DEFAULT 0,
                price_type   TEXT DEFAULT 'hora',
                currency     TEXT DEFAULT 'CRC',
                province     TEXT,
                canton       TEXT,
                district     TEXT,
                location_description TEXT,
                experience_years INTEGER DEFAULT 0,
                skills       TEXT,
                availability TEXT,
                images       TEXT,
                contact_name  TEXT,
                contact_phone TEXT,
                contact_email TEXT,
                contact_whatsapp TEXT,
                website      TEXT,
                pricing_plan_id INTEGER NOT NULL DEFAULT 1,
                is_active    INTEGER DEFAULT 1,
                is_featured  INTEGER DEFAULT 0,
                views_count  INTEGER DEFAULT 0,
                start_date   TEXT,
                end_date     TEXT,
                payment_status TEXT DEFAULT 'pending',
                payment_id   TEXT,
                created_at   TEXT DEFAULT (datetime('now')),
                updated_at   TEXT DEFAULT (datetime('now'))
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sl_agent    ON service_listings(agent_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sl_active   ON service_listings(is_active)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sl_category ON service_listings(category_id)");

            // ── Tabla categories compartida (Bienes Raíces + Servicios) ──────
            $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL UNIQUE,
                icon          TEXT DEFAULT NULL,
                active        INTEGER DEFAULT 1,
                display_order INTEGER DEFAULT 0,
                created_at    TEXT DEFAULT (datetime('now'))
            )");
            // Seed categorías de servicios profesionales si la tabla está vacía
            if ((int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn() === 0) {
                $pdo->exec("INSERT INTO categories (name, icon, active, display_order) VALUES
                    ('SERV: Abogados y Servicios Legales',  'fa-gavel',              1, 200),
                    ('SERV: Contabilidad y Finanzas',        'fa-calculator',         1, 201),
                    ('SERV: Mantenimiento del Hogar',        'fa-tools',              1, 202),
                    ('SERV: Plomería y Electricidad',        'fa-plug',               1, 203),
                    ('SERV: Limpieza del Hogar',             'fa-broom',              1, 204),
                    ('SERV: Shuttle y Transporte',           'fa-shuttle-van',        1, 205),
                    ('SERV: Fletes y Mudanzas',              'fa-truck',              1, 206),
                    ('SERV: Tutorías y Clases',              'fa-chalkboard-teacher', 1, 207),
                    ('SERV: Diseño y Creatividad',           'fa-paint-brush',        1, 208),
                    ('SERV: Tecnología y Sistemas',          'fa-laptop-code',        1, 209),
                    ('SERV: Salud y Bienestar',              'fa-heartbeat',          1, 210),
                    ('SERV: Fotografía y Video',             'fa-camera',             1, 211),
                    ('SERV: Eventos y Catering',             'fa-glass-cheers',       1, 212),
                    ('SERV: Jardinería y Zonas Verdes',      'fa-leaf',               1, 213),
                    ('SERV: Seguridad y Vigilancia',         'fa-shield-alt',         1, 214),
                    ('SERV: Otros Servicios',                'fa-concierge-bell',     1, 215),
                    ('BR: Casas en Venta',                   'fa-home',               1, 100),
                    ('BR: Casas en Alquiler',                'fa-home',               1, 101),
                    ('BR: Apartamentos en Venta',            'fa-building',           1, 102),
                    ('BR: Apartamentos en Alquiler',         'fa-building',           1, 103),
                    ('BR: Locales Comerciales en Venta',     'fa-store',              1, 104),
                    ('BR: Locales Comerciales en Alquiler',  'fa-store',              1, 105),
                    ('BR: Oficinas en Venta',                'fa-briefcase',          1, 106),
                    ('BR: Oficinas en Alquiler',             'fa-briefcase',          1, 107),
                    ('BR: Terrenos en Venta',                'fa-map',                1, 108),
                    ('BR: Lotes en Venta',                   'fa-map-marked-alt',     1, 109),
                    ('BR: Bodegas en Venta',                 'fa-warehouse',          1, 110),
                    ('BR: Bodegas en Alquiler',              'fa-warehouse',          1, 111),
                    ('BR: Fincas en Venta',                  'fa-tractor',            1, 113),
                    ('BR: Otros Bienes Raíces',              'fa-question-circle',    1, 117)");
            }

            // ── Migraciones silenciosas para tablas de portales que ya pueden existir ──
            // Agrega columnas que los dashboards necesitan pero pueden faltar en tablas antiguas
            try {
                $relCols = array_column($pdo->query("PRAGMA table_info(real_estate_listings)")->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (!in_array('agent_id', $relCols))
                    $pdo->exec("ALTER TABLE real_estate_listings ADD COLUMN agent_id INTEGER");
                if (!in_array('latitude', $relCols))
                    $pdo->exec("ALTER TABLE real_estate_listings ADD COLUMN latitude REAL DEFAULT NULL");
                if (!in_array('longitude', $relCols))
                    $pdo->exec("ALTER TABLE real_estate_listings ADD COLUMN longitude REAL DEFAULT NULL");
            } catch (Throwable $_e) {}
            try {
                $lpCols = array_column($pdo->query("PRAGMA table_info(listing_pricing)")->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (!in_array('payment_methods', $lpCols))
                    $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT");
                if (!in_array('max_photos', $lpCols))
                    $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN max_photos INTEGER DEFAULT 3");
                if (!in_array('applies_to', $lpCols))
                    $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN applies_to TEXT DEFAULT 'both'");
            } catch (Throwable $_e) {}

            // Insertar T&C iniciales si no existen
            $types = [
                'cliente', 'vendedor', 'emprendedor',
                'empleos', 'servicios', 'bienes_raices'
            ];
            foreach ($types as $t) {
                $exists = $pdo->prepare("SELECT id FROM terms_conditions WHERE type=? LIMIT 1");
                $exists->execute([$t]);
                if (!$exists->fetchColumn()) {
                    $pdo->prepare("INSERT INTO terms_conditions (type, title, content, version) VALUES (?,?,?,?)")
                        ->execute([$t, tcDefaultTitle($t), tcDefaultContent($t), '1.0']);
                }
            }

            // ── Crear usuario-bot para importaciones si no existe ───────────
            $botEmail = 'bot@compratica.com';
            $hasBot = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $hasBot->execute([$botEmail]);
            if (!$hasBot->fetchColumn()) {
                $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, company_name, company_description, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
                ")->execute([
                    'CompraTica Empleos',
                    $botEmail,
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    'CompraTica Empleos',
                    'Empleos importados automáticamente de diversas fuentes.',
                ]);
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

// ── Términos y Condiciones: helpers ──────────────────────────────────────────

function tcDefaultTitle(string $type): string {
    $titles = [
        'cliente'       => 'Términos y Condiciones para Compradores',
        'vendedor'      => 'Términos y Condiciones para Vendedores (Afiliados)',
        'emprendedor'   => 'Términos y Condiciones para Emprendedores/as',
        'empleos'       => 'Términos y Condiciones para Empleos',
        'servicios'     => 'Términos y Condiciones para Proveedores de Servicios',
        'bienes_raices' => 'Términos y Condiciones para Agentes Inmobiliarios',
    ];
    return $titles[$type] ?? 'Términos y Condiciones';
}

function tcDefaultContent(string $type): string {
    $company  = 'CompraTica';
    $site     = 'compratica.com';
    $email    = 'info@compratica.com';
    $country  = 'Costa Rica';
    $date     = date('d/m/Y');

    $intro = "**Fecha de vigencia:** $date\n\n"
           . "Al registrarse y utilizar la plataforma **$company** ($site), usted declara haber leído, "
           . "comprendido y aceptado los presentes Términos y Condiciones. Si no está de acuerdo con alguna "
           . "de las disposiciones aquí establecidas, le pedimos abstenerse de utilizar nuestros servicios.\n\n"
           . "**$company** es una plataforma de comercio electrónico 100% costarricense, registrada y operando "
           . "conforme a las leyes de la República de $country.\n\n";

    $footer = "\n\n---\n\n"
            . "**Contacto:** Para consultas sobre estos términos, escribinos a $email o visitá $site.\n\n"
            . "**Última actualización:** $date";

    switch ($type) {

        case 'cliente':
            return $intro
. "## 1. Definiciones
- **Usuario/Comprador:** Persona física o jurídica que utiliza CompraTica para adquirir productos o servicios.
- **Vendedor:** Emprendedor/a o afiliado que publica productos en la plataforma.
- **Plataforma:** El sitio web compratica.com y sus aplicaciones asociadas.

## 2. Registro y Cuenta
- Debés ser mayor de 18 años o contar con autorización de tu representante legal para registrarte.
- La información proporcionada en el registro debe ser veraz, actualizada y completa.
- Sos responsable de mantener la confidencialidad de tu contraseña y de todas las actividades realizadas desde tu cuenta.
- CompraTica se reserva el derecho de suspender cuentas con información falsa o que incumplan estos términos.

## 3. Proceso de Compra
- Al confirmar un pedido, aceptás pagar el precio acordado más los costos de envío aplicables.
- Los métodos de pago aceptados son: **SINPE Móvil** y **PayPal**, según lo que cada vendedor configure.
- El comprobante de pago debe subirse en la plataforma dentro de las 24 horas siguientes a la transacción.
- CompraTica no procesa ni custodia los pagos directamente; los mismos se realizan entre comprador y vendedor.

## 4. Entregas y Envíos
- Los tiempos de entrega son responsabilidad de cada vendedor y varían entre 1 y 7 días hábiles.
- El comprador debe verificar el estado del producto al recibirlo y reportar inconvenientes en un plazo de 48 horas.
- En caso de producto no recibido, el comprador debe contactar primero al vendedor y luego a CompraTica si no obtiene respuesta.

## 5. Devoluciones y Garantías
- CompraTica facilita la comunicación entre comprador y vendedor para resolver disputas, pero no garantiza devoluciones.
- Cada vendedor define su propia política de devoluciones, la cual debe estar visible en su tienda.
- En casos de fraude comprobado, CompraTica tomará las acciones pertinentes según la legislación costarricense.

## 6. Uso Aceptable
- Queda prohibido utilizar la plataforma para adquirir productos ilegales, falsificados o que infrinjan derechos de terceros.
- No está permitido realizar pagos fraudulentos, usar comprobantes falsos ni intentar engañar a los vendedores.

## 7. Privacidad de Datos
- Sus datos personales serán tratados conforme a la **Ley 8968 de Protección de la Persona frente al tratamiento de sus datos personales** de Costa Rica.
- CompraTica no vende ni comparte sus datos con terceros sin su consentimiento expreso, salvo requerimiento legal.

## 8. Limitación de Responsabilidad
- CompraTica actúa como intermediario tecnológico. No es parte en las transacciones entre compradores y vendedores.
- La plataforma no se responsabiliza por la calidad, exactitud ni disponibilidad de los productos listados."
. $footer;

        case 'vendedor':
            return $intro
. "## 1. Definiciones
- **Vendedor/Afiliado:** Persona física o jurídica que publica y vende productos o servicios en CompraTica.
- **Espacio de Venta:** Área virtual asignada al vendedor para realizar sus ventas de garaje u otras actividades comerciales.
- **Comisión:** Porcentaje que CompraTica retiene sobre las ventas realizadas según el plan contratado.

## 2. Registro y Activación
- El vendedor debe ser mayor de 18 años y contar con una cédula física o jurídica costarricense vigente.
- La información de registro debe ser real y actualizada; el uso de datos falsos conlleva la cancelación inmediata.
- La activación del espacio de venta requiere el pago de la tarifa de activación correspondiente.

## 3. Publicación de Productos
- Solo se permite publicar productos y servicios lícitos, de tu propiedad o debidamente autorizados para vender.
- Está prohibido publicar: armas, drogas, medicamentos sin receta, productos falsificados, contenido para adultos o cualquier artículo ilegal bajo la legislación costarricense.
- Las fotos e información de los productos deben ser verídicas y no inducir a error al comprador.
- CompraTica se reserva el derecho de remover publicaciones que violen estas normas sin previo aviso.

## 4. Precios y Pagos
- Los precios se publican en colones costarricenses (₡) o dólares (USD) según la configuración del vendedor.
- Los pagos se realizan directamente entre comprador y vendedor; el vendedor debe facilitar su número de SINPE Móvil y/o cuenta PayPal.
- CompraTica cobra una **comisión sobre ventas** según el plan activo del vendedor.

## 5. Gestión de Pedidos
- El vendedor se compromete a confirmar o rechazar pedidos en un plazo máximo de 24 horas.
- El envío o entrega debe realizarse en el tiempo acordado con el comprador.
- Ante disputas, el vendedor debe colaborar con CompraTica para su resolución.

## 6. Propiedad Intelectual
- El vendedor es responsable de contar con los derechos sobre las imágenes, marcas y contenido que publique.
- Al publicar en CompraTica, otorgás una licencia no exclusiva para mostrar dicho contenido en la plataforma.

## 7. Suspensión y Cancelación
- CompraTica puede suspender o cancelar una cuenta vendedora en caso de incumplimiento de estos términos, fraude, o quejas reiteradas de compradores.
- Las tarifas de activación pagadas no son reembolsables una vez activado el espacio.

## 8. Privacidad y Datos
- Los datos personales del vendedor serán tratados conforme a la **Ley 8968** de Costa Rica.
- El vendedor reconoce que su nombre, nombre de tienda y provincia de operación podrán ser visibles públicamente."
. $footer;

        case 'emprendedor':
            return $intro
. "## 1. Definiciones
- **Emprendedor/a:** Persona que utiliza la plataforma CompraTica para publicar y vender sus productos artesanales, creativos o empresariales.
- **Plan de Suscripción:** Modalidad de acceso (Gratuito, Básico o Premium) con funciones y límites distintos.
- **Catálogo:** Espacio virtual personalizado donde el emprendedor/a exhibe sus productos.

## 2. Elegibilidad y Registro
- Podés registrarte como emprendedor/a siendo mayor de 18 años o con autorización de tu representante legal.
- Tu información de contacto, nombre y datos de negocio deben ser reales y estar actualizados.
- Cada emprendedor/a puede tener una sola cuenta activa en la plataforma.

## 3. Planes y Suscripción
- CompraTica ofrece planes **Gratuito, Emprendedor/a y Premium**, con distintas funciones y límites de productos.
- Los planes de pago se cobran mensualmente en colones costarricenses.
- La suscripción se activa al confirmar el pago y finaliza en la fecha indicada si no se renueva.
- No se realizan reembolsos por períodos no utilizados.

## 4. Publicación de Productos
- Solo podés publicar productos que sean de tu autoría, fabricación o cuya venta tengas autorizada legalmente.
- Las imágenes deben ser propias o con licencia de uso; no se permite copiar fotos de otras tiendas.
- Queda prohibido publicar productos ilegales, falsificados, para adultos o que dañen la imagen de terceros.
- CompraTica puede remover publicaciones que incumplan estas normas sin necesidad de aviso previo.

## 5. Ventas y Cobros
- Los compradores pagan directamente al emprendedor/a mediante SINPE Móvil o PayPal.
- El emprendedor/a es responsable de entregar el producto en las condiciones y tiempos acordados.
- Ante disputas con compradores, el emprendedor/a debe actuar de buena fe y con diligencia.

## 6. Comisiones
- Según el plan activo, CompraTica puede cobrar una comisión sobre cada venta realizada.
- Las comisiones serán deducidas o cobradas conforme a lo indicado en la descripción del plan vigente.

## 7. Imagen y Marca
- Al publicar en CompraTica, autorizás el uso de tu contenido (fotos, nombre de negocio, descripción) para fines promocionales de la plataforma.
- Tu logo, fotos y marca siguen siendo de tu propiedad.

## 8. Privacidad
- Tus datos personales serán tratados con estricta confidencialidad conforme a la **Ley 8968** costarricense.
- Tu nombre de negocio, provincia y categoría de productos podrán ser visibles públicamente en el catálogo."
. $footer;

        case 'empleos':
            return $intro
. "## 1. Definiciones
- **Empleador:** Empresa o persona que publica ofertas laborales en CompraTica.
- **Candidato:** Persona que utiliza la plataforma para buscar empleo y postularse a ofertas.
- **Oferta Laboral:** Publicación de un puesto de trabajo con sus requisitos, salario y condiciones.

## 2. Para Empleadores
- La empresa o persona que publica una oferta debe tener existencia legal en Costa Rica o estar autorizada para contratar en el país.
- Las ofertas deben describir el puesto con veracidad; está prohibido publicar ofertas falsas, engañosas o discriminatorias.
- CompraTica se reserva el derecho de eliminar ofertas que incumplan la **Ley 2694 (Código de Trabajo)** o cualquier normativa laboral vigente.
- Está prohibido publicar ofertas que soliciten pagos a candidatos como condición de empleo.

## 3. Para Candidatos
- Al postularse a una oferta, aceptás que tus datos de contacto sean compartidos con el empleador correspondiente.
- La información en tu perfil o postulación debe ser veraz; el uso de datos falsos en un proceso laboral puede tener consecuencias legales.
- CompraTica no garantiza la obtención de empleo ni la idoneidad de las ofertas publicadas.

## 4. Proceso de Selección
- El proceso de selección es responsabilidad exclusiva del empleador.
- CompraTica no participa ni es responsable de las decisiones de contratación.
- Cualquier acuerdo laboral se establece directamente entre el empleador y el candidato.

## 5. Contenido Prohibido
- Está prohibido publicar ofertas con criterios discriminatorios por raza, género, religión, orientación sexual, discapacidad, edad o cualquier otro factor protegido por la ley costarricense.
- No se permite publicar ofertas de actividades ilegales, pirámides financieras ni esquemas de trabajo forzoso.

## 6. Privacidad de Datos
- Los datos de candidatos serán compartidos únicamente con el empleador de la oferta a la que se postulan.
- Los empleadores se comprometen a tratar los datos de los candidatos conforme a la **Ley 8968**.
- CompraTica no cede datos a terceros con fines publicitarios sin consentimiento expreso.

## 7. Tarifas
- La publicación básica de ofertas laborales es gratuita.
- Las opciones de destacado o mayor visibilidad tienen costo según el plan vigente al momento de la publicación."
. $footer;

        case 'servicios':
            return $intro
. "## 1. Definiciones
- **Proveedor de Servicios:** Persona física o jurídica que ofrece sus servicios profesionales o técnicos en CompraTica.
- **Cliente:** Persona que contacta o contrata servicios a través de la plataforma.
- **Servicio:** Toda actividad de valor económico ofrecida por el proveedor (plomería, diseño, clases, etc.).

## 2. Registro como Proveedor
- Debes ser mayor de 18 años y contar con capacidad legal para ejercer el servicio que ofreces.
- Si tu servicio requiere habilitación profesional (médico, abogado, ingeniero, etc.), debés contar con la acreditación correspondiente y es tu responsabilidad mantenerla vigente.
- La información de tu perfil debe ser veraz, incluyendo descripción, precios, zona de cobertura y fotografías.

## 3. Publicación de Servicios
- Solo podés publicar servicios que estés capacitado y legalmente habilitado para prestar.
- Está prohibido publicar: servicios ilegales, actividades que violen la ley costarricense, servicios engañosos o que induzcan a error.
- CompraTica puede remover publicaciones que no cumplan con estas normas.

## 4. Contratación y Pagos
- Las condiciones específicas del servicio (precio, horario, entregables) se acuerdan directamente entre proveedor y cliente.
- Los pagos se realizan directamente entre las partes mediante SINPE Móvil o PayPal.
- CompraTica actúa como intermediario de visibilidad, no como parte del contrato de servicio.

## 5. Calidad y Responsabilidad
- El proveedor es el único responsable de la calidad y ejecución del servicio prestado.
- CompraTica no garantiza la calidad ni los resultados de los servicios ofrecidos en la plataforma.
- Ante incumplimientos, el cliente debe resolver directamente con el proveedor o acudir a las instancias legales correspondientes.

## 6. Reseñas y Reputación
- Los clientes pueden dejar reseñas sobre los servicios recibidos.
- Está prohibido publicar reseñas falsas, ya sea positivas o negativas, con fines de manipulación.
- CompraTica puede eliminar reseñas que no cumplan con los estándares de la plataforma.

## 7. Privacidad
- Los datos del proveedor (nombre, teléfono, zona de cobertura) podrán ser visibles en el catálogo público.
- Los datos de contacto de los clientes serán compartidos únicamente para facilitar la comunicación sobre el servicio contratado."
. $footer;

        case 'bienes_raices':
            return $intro
. "## 1. Definiciones
- **Agente Inmobiliario:** Persona física o jurídica que publica propiedades en venta o alquiler en CompraTica.
- **Propiedad:** Bien inmueble (casa, apartamento, lote, local, finca, etc.) listado en la plataforma.
- **Interesado:** Persona que contacta al agente a través de CompraTica para obtener información de una propiedad.

## 2. Registro y Habilitación
- El agente debe ser mayor de 18 años y actuar dentro del marco legal costarricense.
- Si el agente representa a una empresa inmobiliaria, debe contar con autorización de la misma.
- CompraTica recomienda a los agentes estar inscritos en el **SUGEF** o en el colegio profesional correspondiente, aunque no lo exige como requisito de plataforma.

## 3. Publicación de Propiedades
- Solo se pueden publicar propiedades sobre las que el agente tenga mandato o autorización del propietario.
- La información publicada (precio, área, descripción, ubicación, fotos) debe ser veraz y actualizada.
- Está prohibido publicar propiedades inexistentes, con precios engañosos o con información que induzca a error.
- CompraTica puede remover anuncios que presenten inconsistencias o que sean reportados como fraudulentos.

## 4. Transacciones Inmobiliarias
- CompraTica actúa exclusivamente como plataforma de visibilidad; no participa en la negociación ni en el cierre de transacciones.
- El agente es el único responsable de la correcta tramitación legal de la compraventa o alquiler ante el Registro Nacional y demás autoridades.
- CompraTica no certifica la situación registral, cargas ni derechos sobre las propiedades listadas.

## 5. Comunicación con Interesados
- Al publicar en CompraTica, el agente acepta ser contactado por interesados a través de los medios indicados en el anuncio.
- El agente se compromete a responder consultas de buena fe y en un plazo razonable.

## 6. Comisiones y Tarifas de Plataforma
- La publicación básica de propiedades puede ser gratuita o de pago según el plan vigente.
- CompraTica NO cobra comisión sobre el precio de venta o alquiler de las propiedades; ese acuerdo es exclusivo entre el agente y su cliente.

## 7. Privacidad
- El nombre del agente, empresa representada, teléfono y correo de contacto podrán ser visibles públicamente.
- Los datos de los interesados que contacten al agente serán tratados con confidencialidad y usados solo para la gestión del interés expresado."
. $footer;

        default:
            return $intro . "Términos generales de uso de la plataforma CompraTica." . $footer;
    }
}
?>
