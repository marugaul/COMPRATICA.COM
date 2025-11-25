<?php
/**
 * Buscador Unificado de Lugares
 * Busca en todas las bases de datos y permite exportar a Excel
 */

// Verificar qué tablas existen
$tables = [
    'foursquare' => false,
    'yelp' => false,
    'paginas_amarillas' => false,
    'osm' => false
];

try {
    $tables['foursquare'] = (bool)$pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
    $tables['yelp'] = (bool)$pdo->query("SHOW TABLES LIKE 'lugares_yelp'")->fetch();
    $tables['paginas_amarillas'] = (bool)$pdo->query("SHOW TABLES LIKE 'lugares_paginas_amarillas'")->fetch();
    $tables['osm'] = (bool)$pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
} catch (Exception $e) {}

// Obtener categorías de cada tabla
$categorias_por_fuente = [];

if ($tables['foursquare']) {
    $categorias_por_fuente['foursquare'] = $pdo->query("
        SELECT DISTINCT categoria as name, COUNT(*) as count
        FROM lugares_foursquare WHERE categoria IS NOT NULL AND categoria != ''
        GROUP BY categoria ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if ($tables['yelp']) {
    $categorias_por_fuente['yelp'] = $pdo->query("
        SELECT DISTINCT categoria as name, COUNT(*) as count
        FROM lugares_yelp WHERE categoria IS NOT NULL AND categoria != ''
        GROUP BY categoria ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if ($tables['paginas_amarillas']) {
    $categorias_por_fuente['paginas_amarillas'] = $pdo->query("
        SELECT DISTINCT categoria as name, COUNT(*) as count
        FROM lugares_paginas_amarillas WHERE categoria IS NOT NULL AND categoria != ''
        GROUP BY categoria ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if ($tables['osm']) {
    $categorias_por_fuente['osm'] = $pdo->query("
        SELECT DISTINCT categoria as name, COUNT(*) as count
        FROM lugares_comerciales WHERE categoria IS NOT NULL AND categoria != ''
        GROUP BY categoria ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar búsqueda
$resultados = [];
$total_resultados = 0;
$busqueda_realizada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['buscar'])) {
    $busqueda_realizada = true;
    $fuentes = $_REQUEST['fuentes'] ?? [];
    $categorias = $_REQUEST['categorias'] ?? [];
    $texto = $_REQUEST['texto'] ?? '';
    $solo_telefono = isset($_REQUEST['solo_telefono']);
    $solo_email = isset($_REQUEST['solo_email']);

    if (empty($fuentes)) {
        $fuentes = array_keys(array_filter($tables));
    }

    foreach ($fuentes as $fuente) {
        if (!$tables[$fuente]) continue;

        $table_name = match($fuente) {
            'foursquare' => 'lugares_foursquare',
            'yelp' => 'lugares_yelp',
            'paginas_amarillas' => 'lugares_paginas_amarillas',
            'osm' => 'lugares_comerciales',
            default => null
        };

        if (!$table_name) continue;

        $where = [];
        $params = [];

        if (!empty($texto)) {
            $where[] = "(nombre LIKE ? OR direccion LIKE ? OR ciudad LIKE ?)";
            $params[] = "%$texto%";
            $params[] = "%$texto%";
            $params[] = "%$texto%";
        }

        if (!empty($categorias)) {
            $placeholders = implode(',', array_fill(0, count($categorias), '?'));
            $where[] = "categoria IN ($placeholders)";
            $params = array_merge($params, $categorias);
        }

        if ($solo_telefono) {
            $where[] = "telefono IS NOT NULL AND telefono != ''";
        }

        if ($solo_email) {
            $where[] = "email IS NOT NULL AND email != ''";
        }

        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT id, nombre, categoria, telefono, email, direccion, ciudad, '$fuente' as fuente
                FROM $table_name $where_sql LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultados = array_merge($resultados, $rows);
    }

    $total_resultados = count($resultados);
}

// Exportar a Excel
if (isset($_REQUEST['exportar']) && !empty($resultados)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lugares_' . date('Y-m-d_His') . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    // Headers
    fputcsv($output, ['Nombre', 'Categoria', 'Telefono', 'Email', 'Direccion', 'Ciudad', 'Fuente']);

    // Data
    foreach ($resultados as $row) {
        fputcsv($output, [
            $row['nombre'],
            $row['categoria'],
            $row['telefono'],
            $row['email'],
            $row['direccion'],
            $row['ciudad'],
            $row['fuente']
        ]);
    }

    fclose($output);
    exit;
}
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-search"></i> Buscar y Exportar Lugares</h2>
        <p class="text-muted">Busca en todas las bases de datos y exporta los resultados a Excel</p>
    </div>
</div>

<!-- Resumen de bases de datos -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card <?= $tables['foursquare'] ? 'primary' : 'secondary' ?>" style="<?= $tables['foursquare'] ? 'background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);' : '' ?>">
            <i class="fas fa-map-marker-alt fa-2x"></i>
            <h3><?= $tables['foursquare'] ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_foursquare")->fetchColumn()) : '0' ?></h3>
            <p>Foursquare</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: <?= $tables['yelp'] ? 'linear-gradient(135deg, #d32323 0%, #af1d1d 100%)' : '#6c757d' ?>;">
            <i class="fab fa-yelp fa-2x"></i>
            <h3><?= $tables['yelp'] ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_yelp")->fetchColumn()) : '0' ?></h3>
            <p>Yelp</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: <?= $tables['paginas_amarillas'] ? 'linear-gradient(135deg, #f5c518 0%, #d4a60e 100%)' : '#6c757d' ?>; <?= $tables['paginas_amarillas'] ? 'color: #1a1a1a;' : '' ?>">
            <i class="fas fa-book fa-2x"></i>
            <h3><?= $tables['paginas_amarillas'] ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas")->fetchColumn()) : '0' ?></h3>
            <p>Páginas Amarillas</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card <?= $tables['osm'] ? 'success' : 'secondary' ?>">
            <i class="fas fa-map fa-2x"></i>
            <h3><?= $tables['osm'] ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn()) : '0' ?></h3>
            <p>OpenStreetMap</p>
        </div>
    </div>
</div>

<!-- Formulario de búsqueda -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros de Búsqueda</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="searchForm">
            <div class="row">
                <!-- Fuentes de datos -->
                <div class="col-md-3">
                    <label class="form-label"><strong>Fuentes de Datos</strong></label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="fuentes[]" value="foursquare" id="src_foursquare" <?= $tables['foursquare'] ? 'checked' : 'disabled' ?>>
                        <label class="form-check-label" for="src_foursquare">
                            <i class="fas fa-map-marker-alt text-primary"></i> Foursquare
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="fuentes[]" value="yelp" id="src_yelp" <?= $tables['yelp'] ? 'checked' : 'disabled' ?>>
                        <label class="form-check-label" for="src_yelp">
                            <i class="fab fa-yelp" style="color: #d32323;"></i> Yelp
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="fuentes[]" value="paginas_amarillas" id="src_pa" <?= $tables['paginas_amarillas'] ? 'checked' : 'disabled' ?>>
                        <label class="form-check-label" for="src_pa">
                            <i class="fas fa-book" style="color: #f5c518;"></i> Páginas Amarillas
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="fuentes[]" value="osm" id="src_osm" <?= $tables['osm'] ? 'checked' : 'disabled' ?>>
                        <label class="form-check-label" for="src_osm">
                            <i class="fas fa-map text-success"></i> OpenStreetMap
                        </label>
                    </div>
                </div>

                <!-- Categorías -->
                <div class="col-md-5">
                    <label class="form-label"><strong>Categorías</strong></label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px;">
                        <?php
                        $todas_categorias = [];
                        foreach ($categorias_por_fuente as $fuente => $cats) {
                            foreach ($cats as $cat) {
                                $name = $cat['name'];
                                if (!isset($todas_categorias[$name])) {
                                    $todas_categorias[$name] = 0;
                                }
                                $todas_categorias[$name] += $cat['count'];
                            }
                        }
                        arsort($todas_categorias);
                        foreach ($todas_categorias as $cat_name => $count):
                        ?>
                        <div class="form-check">
                            <input class="form-check-input cat-check" type="checkbox" name="categorias[]"
                                   value="<?= h($cat_name) ?>" id="cat_<?= md5($cat_name) ?>">
                            <label class="form-check-label" for="cat_<?= md5($cat_name) ?>">
                                <?= h($cat_name) ?> <span class="badge bg-secondary"><?= number_format($count) ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllCats()">Todas</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllCats()">Ninguna</button>
                    </div>
                </div>

                <!-- Opciones adicionales -->
                <div class="col-md-4">
                    <label class="form-label"><strong>Buscar por texto</strong></label>
                    <input type="text" name="texto" class="form-control mb-3"
                           placeholder="Nombre, dirección o ciudad..."
                           value="<?= h($_REQUEST['texto'] ?? '') ?>">

                    <label class="form-label"><strong>Filtros adicionales</strong></label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="solo_telefono" id="solo_telefono"
                               <?= isset($_REQUEST['solo_telefono']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="solo_telefono">
                            <i class="fas fa-phone text-success"></i> Solo con teléfono
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="solo_email" id="solo_email"
                               <?= isset($_REQUEST['solo_email']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="solo_email">
                            <i class="fas fa-envelope text-info"></i> Solo con email
                        </label>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if ($busqueda_realizada && $total_resultados > 0): ?>
                    <button type="submit" name="exportar" value="1" class="btn btn-success btn-lg ms-2">
                        <i class="fas fa-file-excel"></i> Exportar a Excel (<?= number_format($total_resultados) ?>)
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Resultados -->
<?php if ($busqueda_realizada): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Resultados de Búsqueda</span>
        <span class="badge bg-primary"><?= number_format($total_resultados) ?> lugares encontrados</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($resultados)): ?>
        <div class="alert alert-info m-3">
            <i class="fas fa-info-circle"></i> No se encontraron resultados con los filtros seleccionados.
        </div>
        <?php else: ?>
        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
            <table class="table table-hover table-sm mb-0">
                <thead class="bg-light sticky-top">
                    <tr>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Ciudad</th>
                        <th>Categoría</th>
                        <th>Fuente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $row): ?>
                    <tr>
                        <td>
                            <strong><?= h($row['nombre']) ?></strong>
                            <br><small class="text-muted"><?= h($row['direccion']) ?></small>
                        </td>
                        <td>
                            <?php if ($row['telefono']): ?>
                            <a href="tel:<?= h($row['telefono']) ?>">
                                <i class="fas fa-phone text-success"></i> <?= h($row['telefono']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['email']): ?>
                            <a href="mailto:<?= h($row['email']) ?>">
                                <i class="fas fa-envelope text-info"></i>
                                <?= strlen($row['email']) > 25 ? h(substr($row['email'], 0, 25)) . '...' : h($row['email']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($row['ciudad']) ?></td>
                        <td><span class="badge bg-secondary"><?= h($row['categoria']) ?></span></td>
                        <td>
                            <?php
                            $badge_color = match($row['fuente']) {
                                'foursquare' => 'bg-purple',
                                'yelp' => 'bg-danger',
                                'paginas_amarillas' => 'bg-warning text-dark',
                                'osm' => 'bg-success',
                                default => 'bg-secondary'
                            };
                            $icon = match($row['fuente']) {
                                'foursquare' => 'fas fa-map-marker-alt',
                                'yelp' => 'fab fa-yelp',
                                'paginas_amarillas' => 'fas fa-book',
                                'osm' => 'fas fa-map',
                                default => 'fas fa-database'
                            };
                            ?>
                            <span class="badge <?= $badge_color ?>" style="<?= $row['fuente'] === 'foursquare' ? 'background: #8b5cf6;' : '' ?>">
                                <i class="<?= $icon ?>"></i> <?= ucfirst($row['fuente']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
function selectAllCats() {
    document.querySelectorAll('.cat-check').forEach(cb => cb.checked = true);
}

function deselectAllCats() {
    document.querySelectorAll('.cat-check').forEach(cb => cb.checked = false);
}
</script>

<style>
.bg-purple {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}
</style>
