<?php
// Gestión de Blacklist Global

// Verificar si la tabla existe
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'email_blacklist'")->fetch();

    if (!$tableExists) {
        ?>
        <div class="alert alert-warning">
            <h4><i class="fas fa-exclamation-triangle"></i> Tabla de Blacklist No Existe</h4>
            <p>La tabla <code>email_blacklist</code> aún no ha sido creada.</p>
            <p>
                <a href="../create_blacklist_table.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Crear Tabla Ahora
                </a>
            </p>
        </div>
        <?php
        return;
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    return;
}

// Obtener estadísticas
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN source = 'unsubscribe' THEN 1 END) as unsubscribes,
        COUNT(CASE WHEN source = 'manual' THEN 1 END) as manual,
        COUNT(CASE WHEN source = 'bounce' THEN 1 END) as bounces,
        COUNT(CASE WHEN source = 'spam_complaint' THEN 1 END) as spam
    FROM email_blacklist
")->fetch(PDO::FETCH_ASSOC);

// Paginación
$page = $_GET['bl_page'] ?? 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Búsqueda
$search = $_GET['bl_search'] ?? '';
$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = "WHERE email LIKE ? OR reason LIKE ? OR notes LIKE ?";
    $searchParam = "%{$search}%";
    $params = [$searchParam, $searchParam, $searchParam];
}

// Obtener lista de emails
$stmt = $pdo->prepare("
    SELECT * FROM email_blacklist
    {$whereClause}
    ORDER BY created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$blacklist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total para paginación
$totalQuery = "SELECT COUNT(*) FROM email_blacklist {$whereClause}";
$stmtTotal = $pdo->prepare($totalQuery);
$stmtTotal->execute($params);
$total = $stmtTotal->fetchColumn();
$totalPages = ceil($total / $perPage);
?>

<h1 class="mb-4"><i class="fas fa-ban"></i> Blacklist Global de Emails</h1>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?= number_format($stats['total']) ?></h3>
                <p class="mb-0">Total en Blacklist</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning"><?= number_format($stats['unsubscribes']) ?></h3>
                <p class="mb-0">Desuscripciones</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-info"><?= number_format($stats['manual']) ?></h3>
                <p class="mb-0">Agregados Manual</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-danger"><?= number_format($stats['bounces']) ?></h3>
                <p class="mb-0">Rebotes</p>
            </div>
        </div>
    </div>
</div>

<!-- Barra de herramientas -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <form method="GET" action="" class="d-flex">
                    <input type="hidden" name="page" value="blacklist">
                    <input type="text" name="bl_search" class="form-control me-2"
                           placeholder="Buscar email..." value="<?= h($search) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if ($search): ?>
                        <a href="?page=blacklist" class="btn btn-secondary ms-2">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
                    <i class="fas fa-plus"></i> Agregar Email
                </button>
                <a href="../create_blacklist_table.php" class="btn btn-outline-secondary">
                    <i class="fas fa-database"></i> Ver Tabla
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de Blacklist -->
<div class="card">
    <div class="card-body">
        <?php if (empty($blacklist)): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5>No hay emails en blacklist</h5>
                <?php if ($search): ?>
                    <p class="text-muted">No se encontraron resultados para "<?= h($search) ?>"</p>
                <?php else: ?>
                    <p class="text-muted">Todos los emails pueden recibir campañas</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Razón</th>
                            <th>Origen</th>
                            <th>Fecha</th>
                            <th>IP</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blacklist as $item): ?>
                            <tr>
                                <td><strong><?= h($item['email']) ?></strong></td>
                                <td>
                                    <small class="text-muted"><?= h($item['reason'] ?? 'Sin razón') ?></small>
                                    <?php if ($item['notes']): ?>
                                        <br><small class="text-info">Nota: <?= h($item['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badges = [
                                        'unsubscribe' => 'bg-warning',
                                        'manual' => 'bg-info',
                                        'bounce' => 'bg-danger',
                                        'spam_complaint' => 'bg-dark',
                                        'migration' => 'bg-secondary'
                                    ];
                                    $badgeClass = $badges[$item['source']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst(h($item['source'])) ?></span>
                                </td>
                                <td><small><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></small></td>
                                <td><small class="text-muted"><?= h($item['ip_address'] ?? 'N/A') ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="removeFromBlacklist(<?= $item['id'] ?>, '<?= h($item['email']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=blacklist&bl_page=<?= $page - 1 ?>&bl_search=<?= urlencode($search) ?>">
                                    Anterior
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=blacklist&bl_page=<?= $i ?>&bl_search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=blacklist&bl_page=<?= $page + 1 ?>&bl_search=<?= urlencode($search) ?>">
                                    Siguiente
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Agregar Email -->
<div class="modal fade" id="addBlacklistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="blacklist_api.php">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Email a Blacklist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required
                               placeholder="ejemplo@dominio.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Razón</label>
                        <input type="text" name="reason" class="form-control"
                               placeholder="Ej: Solicitó no recibir emails">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notas (opcional)</label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Notas adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Agregar a Blacklist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function removeFromBlacklist(id, email) {
    if (!confirm(`¿Está seguro de eliminar "${email}" de la blacklist?\n\nEste email podrá volver a recibir campañas.`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'blacklist_api.php';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'remove';
    form.appendChild(actionInput);

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);

    document.body.appendChild(form);
    form.submit();
}
</script>
