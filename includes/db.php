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
                        'Plan Emprendedora',
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
?>
