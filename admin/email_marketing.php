<?php
/**
 * Email Marketing System - Admin Panel
 * Sistema de envío masivo de correos con tracking
 */

// Cargar configuración (que ya maneja sesiones)
require_once __DIR__ . '/../includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Cargar configuración de BD
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$page = $_GET['page'] ?? 'dashboard';
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// Helper function
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Marketing - CompraTica Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #dc2626;
            --secondary: #0891b2;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            color: #cbd5e1;
            padding: 0;
        }

        .sidebar-header {
            background: linear-gradient(135deg, var(--primary) 0%, #b91c1c 100%);
            padding: 25px 20px;
            color: white;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .sidebar-menu li a {
            display: block;
            padding: 15px 25px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background-color: rgba(220, 38, 38, 0.1);
            border-left-color: var(--primary);
            color: #fbbf24;
        }

        .sidebar-menu li a i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #b91c1c 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
            font-weight: bold;
        }

        .stat-card {
            text-align: center;
            padding: 25px;
            border-radius: 12px;
            color: white;
            margin-bottom: 20px;
        }

        .stat-card.primary { background: linear-gradient(135deg, var(--primary) 0%, #b91c1c 100%); }
        .stat-card.success { background: linear-gradient(135deg, var(--success) 0%, #15803d 100%); }
        .stat-card.warning { background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%); }
        .stat-card.info { background: linear-gradient(135deg, var(--secondary) 0%, #0e7490 100%); }

        .stat-card h3 {
            font-size: 36px;
            margin: 10px 0;
            font-weight: bold;
        }

        .stat-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #b91c1c 100%);
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: bold;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        }

        .template-preview {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .template-preview:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .template-preview.selected {
            border-color: var(--primary);
            background-color: #fef3c7;
        }

        .badge-campaign {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-draft { background-color: #94a3b8; color: white; }
        .badge-sending { background-color: #f59e0b; color: white; }
        .badge-completed { background-color: #16a34a; color: white; }
        .badge-failed { background-color: #ef4444; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="sidebar-header">
                    <h3 style="margin: 0;">🇨🇷 CompraTica</h3>
                    <small>Email Marketing</small>
                </div>

                <ul class="sidebar-menu">
                    <li><a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a></li>
                    <li><a href="?page=new-campaign" class="<?= $page === 'new-campaign' ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i> Nueva Campaña
                    </a></li>
                    <li><a href="?page=campaigns" class="<?= $page === 'campaigns' ? 'active' : '' ?>">
                        <i class="fas fa-envelope-open-text"></i> Campañas
                    </a></li>
                    <li><a href="?page=templates" class="<?= $page === 'templates' ? 'active' : '' ?>">
                        <i class="fas fa-file-code"></i> Plantillas
                    </a></li>
                    <li><a href="?page=smtp-config" class="<?= $page === 'smtp-config' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i> Config. SMTP
                    </a></li>
                    <li><a href="?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i> Reportes
                    </a></li>
                    <li><a href="?page=blacklist" class="<?= $page === 'blacklist' ? 'active' : '' ?>">
                        <i class="fas fa-ban"></i> Blacklist
                    </a></li>
                    <li><hr style="border-color: #334155; margin: 20px 15px;"></li>
                    <li><a href="../admin/dashboard.php">
                        <i class="fas fa-arrow-left"></i> Volver al Admin
                    </a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message['type'] ?? 'info' ?> alert-dismissible fade show">
                        <?= h($message['text']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php
                // Router de páginas
                switch ($page) {
                    case 'dashboard':
                        include __DIR__ . '/email_marketing/dashboard.php';
                        break;
                    case 'new-campaign':
                        include __DIR__ . '/email_marketing/new_campaign.php';
                        break;
                    case 'campaigns':
                        include __DIR__ . '/email_marketing/campaigns.php';
                        break;
                    case 'templates':
                        include __DIR__ . '/email_marketing/templates.php';
                        break;
                    case 'smtp-config':
                        include __DIR__ . '/email_marketing/smtp_config.php';
                        break;
                    case 'reports':
                        include __DIR__ . '/email_marketing/reports.php';
                        break;
                    case 'blacklist':
                        include __DIR__ . '/email_marketing/blacklist.php';
                        break;
                    default:
                        echo '<div class="alert alert-warning">Página no encontrada</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</body>
</html>
