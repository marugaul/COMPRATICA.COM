<?php
/**
 * Gestión de Base de Datos de Lugares Comerciales
 * Integrado en Email Marketing
 */

// Verificar si la tabla existe
$table_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
    $table_exists = (bool)$check;
} catch (Exception $e) {
    $table_exists = false;
}

// Obtener estadísticas si la tabla existe
$stats = null;
if ($table_exists) {
    try {
        $stats = [];
        $stats['total'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
        $stats['con_email'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != ''")->fetchColumn();
        $stats['con_telefono'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE telefono != ''")->fetchColumn();

        $stats['por_categoria'] = $pdo->query("
            SELECT categoria, COUNT(*) as total
            FROM lugares_comerciales
            WHERE categoria != ''
            GROUP BY categoria
            ORDER BY total DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stats['por_tipo'] = $pdo->query("
            SELECT tipo, COUNT(*) as total
            FROM lugares_comerciales
            WHERE tipo != ''
            GROUP BY tipo
            ORDER BY total DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stats = null;
    }
}

// Obtener lista de lugares (paginada)
$page_lugares = isset($_GET['lugares_page']) ? max(1, intval($_GET['lugares_page'])) : 1;
$per_page = 20;
$offset = ($page_lugares - 1) * $per_page;

$lugares = [];
$total_lugares = 0;
if ($table_exists) {
    try {
        $filtro_categoria = $_GET['filtro_categoria'] ?? '';
        $filtro_email = isset($_GET['filtro_email']) ? 1 : 0;

        $where = [];
        $params = [];

        if ($filtro_categoria) {
            $where[] = "categoria = ?";
            $params[] = $filtro_categoria;
        }

        if ($filtro_email) {
            $where[] = "email != ''";
        }

        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $total_lugares = $pdo->prepare("SELECT COUNT(*) FROM lugares_comerciales $where_sql");
        $total_lugares->execute($params);
        $total_lugares = $total_lugares->fetchColumn();

        $params[] = $offset;
        $params[] = $per_page;

        $stmt = $pdo->prepare("
            SELECT id, nombre, tipo, categoria, ciudad, provincia, telefono, email, website, created_at
            FROM lugares_comerciales
            $where_sql
            ORDER BY created_at DESC
            LIMIT ?, ?
        ");
        $stmt->execute($params);
        $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $lugares = [];
    }
}

$total_pages = $total_lugares > 0 ? ceil($total_lugares / $per_page) : 0;
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-map-marked-alt"></i> Base de Datos de Lugares Comerciales</h2>
        <p class="text-muted">Gestiona lugares comerciales de Costa Rica importados desde OpenStreetMap</p>

        <!-- Navegación de pestañas -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="?page=importar_lugares">
                    <i class="fas fa-cloud-download-alt"></i> Importar desde OSM
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=enriquecer_emails">
                    <i class="fas fa-envelope"></i> Enriquecer Emails
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="?page=lugares_comerciales">
                    <i class="fas fa-list"></i> Ver Base de Datos
                </a>
            </li>
        </ul>
    </div>
</div>

<?php if (!$table_exists): ?>
    <!-- Tabla no existe - Mostrar instrucciones -->
    <div class="alert alert-warning">
        <h4><i class="fas fa-exclamation-triangle"></i> Tabla no creada</h4>
        <p>La tabla <code>lugares_comerciales</code> aún no existe en la base de datos.</p>

        <h5 class="mt-3">Opción 1: Ejecutar SQL Manualmente</h5>
        <p>Ejecuta este comando en tu base de datos MySQL:</p>
        <pre class="bg-dark text-white p-3" style="max-height: 300px; overflow-y: auto;">CREATE TABLE lugares_comerciales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo VARCHAR(100),
    categoria VARCHAR(100),
    subtipo VARCHAR(100),
    descripcion TEXT,
    direccion VARCHAR(500),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    codigo_postal VARCHAR(20),
    telefono VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    facebook VARCHAR(255),
    instagram VARCHAR(255),
    horario TEXT,
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    osm_id BIGINT,
    osm_type VARCHAR(10),
    capacidad INT,
    estrellas TINYINT,
    wifi BOOLEAN DEFAULT FALSE,
    parking BOOLEAN DEFAULT FALSE,
    discapacidad_acceso BOOLEAN DEFAULT FALSE,
    tarjetas_credito BOOLEAN DEFAULT FALSE,
    delivery BOOLEAN DEFAULT FALSE,
    takeaway BOOLEAN DEFAULT FALSE,
    tags_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_categoria (categoria),
    INDEX idx_ciudad (ciudad),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>

        <h5 class="mt-3">Opción 2: Usar el Importador Web</h5>
        <p>
            <a href="/public_html/importar_lugares_standalone.php" class="btn btn-primary" target="_blank">
                <i class="fas fa-external-link-alt"></i> Abrir Importador
            </a>
        </p>
    </div>

<?php else: ?>
    <!-- Tabla existe - Mostrar estadísticas y gestión -->

    <!-- Estadísticas -->
    <?php if ($stats): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?= number_format($stats['total']) ?></h3>
                    <p class="mb-0">Total Lugares</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?= number_format($stats['con_email']) ?></h3>
                    <p class="mb-0">Con Email</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3><?= number_format($stats['con_telefono']) ?></h3>
                    <p class="mb-0">Con Teléfono</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h3><?= $stats['con_email'] > 0 ? number_format(($stats['con_email']/$stats['total'])*100, 1) : 0 ?>%</h3>
                    <p class="mb-0">Cobertura Email</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Distribución por categoría y tipo -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📂 Por Categoría</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <?php foreach ($stats['por_categoria'] as $cat): ?>
                        <tr>
                            <td><strong><?= h($cat['categoria']) ?></strong></td>
                            <td class="text-end"><?= number_format($cat['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🏷️ Top 10 Tipos</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <?php foreach ($stats['por_tipo'] as $tipo): ?>
                        <tr>
                            <td><?= h($tipo['tipo']) ?></td>
                            <td class="text-end"><span class="badge bg-primary"><?= number_format($tipo['total']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros y acciones -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="lugares-comerciales">

                <div class="col-md-3">
                    <label class="form-label">Categoría</label>
                    <select name="filtro_categoria" class="form-select">
                        <option value="">Todas</option>
                        <option value="amenity" <?= ($_GET['filtro_categoria'] ?? '') === 'amenity' ? 'selected' : '' ?>>Amenity (Servicios)</option>
                        <option value="shop" <?= ($_GET['filtro_categoria'] ?? '') === 'shop' ? 'selected' : '' ?>>Shop (Tiendas)</option>
                        <option value="tourism" <?= ($_GET['filtro_categoria'] ?? '') === 'tourism' ? 'selected' : '' ?>>Tourism (Turismo)</option>
                        <option value="office" <?= ($_GET['filtro_categoria'] ?? '') === 'office' ? 'selected' : '' ?>>Office (Oficinas)</option>
                        <option value="leisure" <?= ($_GET['filtro_categoria'] ?? '') === 'leisure' ? 'selected' : '' ?>>Leisure (Ocio)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Con Email</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="filtro_email" id="filtroEmail" <?= isset($_GET['filtro_email']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="filtroEmail">
                            Solo lugares con email
                        </label>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </div>

                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <a href="/public_html/importar_lugares_standalone.php" class="btn btn-success w-100" target="_blank">
                        <i class="fas fa-download"></i> Actualizar Datos
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de lugares -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Lugares Comerciales (<?= number_format($total_lugares) ?> total)</h5>
        </div>
        <div class="card-body">
            <?php if (count($lugares) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Ubicación</th>
                                <th>Contacto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lugares as $lugar): ?>
                            <tr>
                                <td>
                                    <strong><?= h($lugar['nombre']) ?></strong>
                                    <br><small class="text-muted"><?= h($lugar['categoria']) ?></small>
                                </td>
                                <td><?= h($lugar['tipo']) ?></td>
                                <td>
                                    <?= h($lugar['ciudad']) ?><?= $lugar['provincia'] ? ', ' . h($lugar['provincia']) : '' ?>
                                </td>
                                <td>
                                    <?php if ($lugar['email']): ?>
                                        <i class="fas fa-envelope text-success"></i> <?= h($lugar['email']) ?><br>
                                    <?php endif; ?>
                                    <?php if ($lugar['telefono']): ?>
                                        <i class="fas fa-phone text-info"></i> <?= h($lugar['telefono']) ?>
                                    <?php endif; ?>
                                    <?php if ($lugar['website']): ?>
                                        <br><a href="<?= h($lugar['website']) ?>" target="_blank"><i class="fas fa-globe"></i> Sitio web</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="verDetalles(<?= $lugar['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page_lugares > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=lugares-comerciales&lugares_page=<?= $page_lugares - 1 ?><?= $filtro_categoria ? '&filtro_categoria=' . urlencode($filtro_categoria) : '' ?><?= $filtro_email ? '&filtro_email=1' : '' ?>">Anterior</a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page_lugares - 2); $i <= min($total_pages, $page_lugares + 2); $i++): ?>
                        <li class="page-item <?= $i == $page_lugares ? 'active' : '' ?>">
                            <a class="page-link" href="?page=lugares-comerciales&lugares_page=<?= $i ?><?= $filtro_categoria ? '&filtro_categoria=' . urlencode($filtro_categoria) : '' ?><?= $filtro_email ? '&filtro_email=1' : '' ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($page_lugares < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=lugares-comerciales&lugares_page=<?= $page_lugares + 1 ?><?= $filtro_categoria ? '&filtro_categoria=' . urlencode($filtro_categoria) : '' ?><?= $filtro_email ? '&filtro_email=1' : '' ?>">Siguiente</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info">
                    No se encontraron lugares con los filtros seleccionados.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function verDetalles(id) {
    // TODO: Implementar modal con detalles completos
    alert('Ver detalles del lugar ID: ' + id);
}
</script>
