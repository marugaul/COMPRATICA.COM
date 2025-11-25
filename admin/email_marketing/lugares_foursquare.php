<?php
/**
 * Ver Base de Datos de Lugares Foursquare
 * Lista y búsqueda de lugares importados desde Foursquare
 */

// Verificar tabla
$table_exists = false;
$total_lugares = 0;
$lugares = [];
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$per_page = 50;
$offset = ($page_num - 1) * $per_page;

// Filtros
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_ciudad = $_GET['ciudad'] ?? '';
$filtro_busqueda = $_GET['q'] ?? '';
$filtro_con_email = isset($_GET['con_email']) && $_GET['con_email'] === '1';
$filtro_con_telefono = isset($_GET['con_telefono']) && $_GET['con_telefono'] === '1';

try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
    $table_exists = (bool)$check;

    if ($table_exists) {
        // Construir query con filtros
        $where = [];
        $params = [];

        if ($filtro_categoria) {
            $where[] = "categoria = ?";
            $params[] = $filtro_categoria;
        }

        if ($filtro_ciudad) {
            $where[] = "ciudad = ?";
            $params[] = $filtro_ciudad;
        }

        if ($filtro_busqueda) {
            $where[] = "(nombre LIKE ? OR direccion LIKE ? OR email LIKE ?)";
            $params[] = "%$filtro_busqueda%";
            $params[] = "%$filtro_busqueda%";
            $params[] = "%$filtro_busqueda%";
        }

        if ($filtro_con_email) {
            $where[] = "email IS NOT NULL AND email != ''";
        }

        if ($filtro_con_telefono) {
            $where[] = "telefono IS NOT NULL AND telefono != ''";
        }

        $where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

        // Contar total
        $count_sql = "SELECT COUNT(*) FROM lugares_foursquare $where_sql";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_lugares = $count_stmt->fetchColumn();

        // Obtener lugares
        $sql = "SELECT * FROM lugares_foursquare $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener categorías únicas para filtro
        $categorias = $pdo->query("SELECT DISTINCT categoria FROM lugares_foursquare WHERE categoria IS NOT NULL ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);

        // Obtener ciudades únicas para filtro
        $ciudades = $pdo->query("SELECT DISTINCT ciudad FROM lugares_foursquare WHERE ciudad IS NOT NULL AND ciudad != '' ORDER BY ciudad")->fetchAll(PDO::FETCH_COLUMN);

        // Estadísticas
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE website IS NOT NULL AND website != ''")->fetchColumn();
    }
} catch (Exception $e) {
    $table_exists = false;
}

$total_pages = ceil($total_lugares / $per_page);
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-list"></i> Base de Datos Foursquare</h2>
        <p class="text-muted">Lugares importados desde Foursquare Places API</p>

        <!-- Navegación de pestañas -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="?page=importar-lugares">
                    <i class="fas fa-cloud-download-alt"></i> Importar OSM
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=importar-foursquare">
                    <i class="fas fa-map-marker-alt"></i> Importar Foursquare
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=enriquecer-emails">
                    <i class="fas fa-envelope"></i> Enriquecer Emails
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="?page=lugares-foursquare">
                    <i class="fas fa-list"></i> Ver BD Foursquare
                </a>
            </li>
        </ul>
    </div>
</div>

<?php if (!$table_exists): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Tabla no existe.</strong>
    <a href="?page=importar-foursquare">Crea la tabla primero</a> e importa datos desde Foursquare.
</div>
<?php else: ?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <i class="fas fa-store fa-2x"></i>
            <h3><?= number_format($total_lugares) ?></h3>
            <p>Total Lugares</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="fas fa-envelope fa-2x"></i>
            <h3><?= number_format($with_email) ?></h3>
            <p>Con Email</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <i class="fas fa-phone fa-2x"></i>
            <h3><?= number_format($with_phone) ?></h3>
            <p>Con Teléfono</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="fas fa-globe fa-2x"></i>
            <h3><?= number_format($with_website) ?></h3>
            <p>Con Website</p>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="lugares-foursquare">

            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control"
                       placeholder="Nombre, dirección, email..."
                       value="<?= h($filtro_busqueda) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= h($cat) ?>" <?= $filtro_categoria === $cat ? 'selected' : '' ?>>
                        <?= h($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Ciudad</label>
                <select name="ciudad" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($ciudades as $ciudad): ?>
                    <option value="<?= h($ciudad) ?>" <?= $filtro_ciudad === $ciudad ? 'selected' : '' ?>>
                        <?= h($ciudad) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Filtros</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="con_email" value="1"
                           id="filtroEmail" <?= $filtro_con_email ? 'checked' : '' ?>>
                    <label class="form-check-label" for="filtroEmail">Solo con email</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="con_telefono" value="1"
                           id="filtroTelefono" <?= $filtro_con_telefono ? 'checked' : '' ?>>
                    <label class="form-check-label" for="filtroTelefono">Solo con teléfono</label>
                </div>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <a href="?page=lugares-foursquare" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Resultados -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list"></i> Lugares
            <span class="badge bg-secondary"><?= number_format($total_lugares) ?> resultados</span>
        </h5>
        <?php if ($total_lugares > 0): ?>
        <a href="?page=exportar-foursquare&<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success">
            <i class="fas fa-file-csv"></i> Exportar CSV
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($lugares)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No se encontraron lugares</h5>
                <p class="text-muted">Intenta con otros filtros o <a href="?page=importar-foursquare">importa más datos</a></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Ciudad</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Website</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lugares as $lugar): ?>
                        <tr>
                            <td>
                                <strong><?= h($lugar['nombre']) ?></strong>
                                <?php if ($lugar['verificado']): ?>
                                    <span class="badge bg-success" title="Verificado"><i class="fas fa-check"></i></span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted"><?= h(substr($lugar['direccion'], 0, 50)) ?><?= strlen($lugar['direccion']) > 50 ? '...' : '' ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= h($lugar['categoria']) ?></span></td>
                            <td><?= h($lugar['ciudad']) ?></td>
                            <td>
                                <?php if ($lugar['telefono']): ?>
                                    <a href="tel:<?= h($lugar['telefono']) ?>" class="text-success">
                                        <i class="fas fa-phone"></i> <?= h($lugar['telefono']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lugar['email']): ?>
                                    <a href="mailto:<?= h($lugar['email']) ?>" class="text-primary">
                                        <i class="fas fa-envelope"></i> <?= h($lugar['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lugar['website']): ?>
                                    <a href="<?= h($lugar['website']) ?>" target="_blank" class="text-info">
                                        <i class="fas fa-globe"></i> Ver
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lugar['rating']): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-star"></i> <?= number_format($lugar['rating'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Paginación">
                <ul class="pagination justify-content-center">
                    <?php if ($page_num > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $page_num - 1])) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page_num - 2);
                    $end_page = min($total_pages, $page_num + 2);
                    ?>

                    <?php if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => 1])) ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $total_pages])) ?>"><?= $total_pages ?></a>
                    </li>
                    <?php endif; ?>

                    <?php if ($page_num < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $page_num + 1])) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <p class="text-center text-muted">
                Mostrando <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $per_page, $total_lugares)) ?>
                de <?= number_format($total_lugares) ?> resultados
            </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
