<?php
/**
 * Visualizar lugares importados desde Páginas Amarillas CR
 */

$table_exists = false;
$lugares = [];
$total = 0;
$categorias = [];
$ciudades = [];

// Filtros
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_ciudad = $_GET['ciudad'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$pagina = max(1, intval($_GET['p'] ?? 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_paginas_amarillas'")->fetch();
    $table_exists = (bool)$check;

    if ($table_exists) {
        // Obtener categorías únicas
        $categorias = $pdo->query("
            SELECT DISTINCT categoria, COUNT(*) as count
            FROM lugares_paginas_amarillas
            WHERE categoria IS NOT NULL AND categoria != ''
            GROUP BY categoria
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Obtener ciudades únicas
        $ciudades = $pdo->query("
            SELECT DISTINCT ciudad, COUNT(*) as count
            FROM lugares_paginas_amarillas
            WHERE ciudad IS NOT NULL AND ciudad != ''
            GROUP BY ciudad
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

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
            $where[] = "(nombre LIKE ? OR direccion LIKE ? OR telefono LIKE ?)";
            $params[] = "%$filtro_busqueda%";
            $params[] = "%$filtro_busqueda%";
            $params[] = "%$filtro_busqueda%";
        }

        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Total de registros
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lugares_paginas_amarillas $where_sql");
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        // Obtener lugares
        $stmt = $pdo->prepare("
            SELECT * FROM lugares_paginas_amarillas
            $where_sql
            ORDER BY created_at DESC
            LIMIT $por_pagina OFFSET $offset
        ");
        $stmt->execute($params);
        $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $table_exists = false;
}

$total_paginas = ceil($total / $por_pagina);
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-book" style="color: #f5c518;"></i> Base de Datos Páginas Amarillas</h2>
        <p class="text-muted">Lugares importados desde Páginas Amarillas Costa Rica</p>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="?page=importar-paginas-amarillas">
                    <i class="fas fa-cloud-download-alt"></i> Importar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="?page=lugares-paginas-amarillas">
                    <i class="fas fa-database"></i> Ver Base de Datos
                </a>
            </li>
        </ul>
    </div>
</div>

<?php if (!$table_exists): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> La tabla <code>lugares_paginas_amarillas</code> no existe.
    <a href="?page=importar-paginas-amarillas">Ir a Importar</a>
</div>
<?php else: ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="lugares-paginas-amarillas">

            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= h($cat['categoria']) ?>" <?= $filtro_categoria === $cat['categoria'] ? 'selected' : '' ?>>
                        <?= h($cat['categoria']) ?> (<?= number_format($cat['count']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Ciudad</label>
                <select name="ciudad" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($ciudades as $ciudad): ?>
                    <option value="<?= h($ciudad['ciudad']) ?>" <?= $filtro_ciudad === $ciudad['ciudad'] ? 'selected' : '' ?>>
                        <?= h($ciudad['ciudad']) ?> (<?= number_format($ciudad['count']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="busqueda" class="form-control"
                       placeholder="Nombre, dirección o teléfono..."
                       value="<?= h($filtro_busqueda) ?>">
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas rápidas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #f5c518 0%, #d4a60e 100%); color: #1a1a1a;">
            <h3><?= number_format($total) ?></h3>
            <p>Lugares Encontrados</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <h3><?= count($categorias) ?></h3>
            <p>Categorías</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <h3><?= count($ciudades) ?></h3>
            <p>Ciudades</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <h3><?= number_format($pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE telefono != '' AND telefono IS NOT NULL")->fetchColumn()) ?></h3>
            <p>Con Teléfono</p>
        </div>
    </div>
</div>

<!-- Tabla de lugares -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Lugares (<?= number_format($total) ?> registros)</span>
        <a href="?page=buscar-lugares" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-download"></i> Exportar a Excel
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Ciudad</th>
                        <th>Categoría</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lugares)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">No se encontraron lugares</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($lugares as $lugar): ?>
                    <tr>
                        <td>
                            <strong><?= h($lugar['nombre']) ?></strong>
                            <br>
                            <small class="text-muted"><?= h($lugar['direccion']) ?></small>
                        </td>
                        <td>
                            <?php if ($lugar['telefono']): ?>
                            <a href="tel:<?= h($lugar['telefono']) ?>">
                                <i class="fas fa-phone text-success"></i> <?= h($lugar['telefono']) ?>
                            </a>
                            <?php if ($lugar['telefono2']): ?>
                            <br><small><?= h($lugar['telefono2']) ?></small>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lugar['email']): ?>
                            <a href="mailto:<?= h($lugar['email']) ?>">
                                <i class="fas fa-envelope text-info"></i> <?= h($lugar['email']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($lugar['ciudad']) ?></td>
                        <td><span class="badge bg-warning text-dark"><?= h($lugar['categoria']) ?></span></td>
                        <td>
                            <?php if ($lugar['website']): ?>
                            <a href="<?= h($lugar['website']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-globe"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination mb-0 justify-content-center">
                <?php if ($pagina > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=lugares-paginas-amarillas&p=<?= $pagina - 1 ?>&categoria=<?= urlencode($filtro_categoria) ?>&ciudad=<?= urlencode($filtro_ciudad) ?>&busqueda=<?= urlencode($filtro_busqueda) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php
                $start = max(1, $pagina - 2);
                $end = min($total_paginas, $pagina + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="?page=lugares-paginas-amarillas&p=<?= $i ?>&categoria=<?= urlencode($filtro_categoria) ?>&ciudad=<?= urlencode($filtro_ciudad) ?>&busqueda=<?= urlencode($filtro_busqueda) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($pagina < $total_paginas): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=lugares-paginas-amarillas&p=<?= $pagina + 1 ?>&categoria=<?= urlencode($filtro_categoria) ?>&ciudad=<?= urlencode($filtro_ciudad) ?>&busqueda=<?= urlencode($filtro_busqueda) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>
