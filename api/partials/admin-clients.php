<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

if ($currentUser->type !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert--error">Acceso denegado. Se requieren permisos de administrador.</div>';
    exit;
}

try {
    $clients = $clientService->getActiveClients();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los clientes. Inténtalo de nuevo.</div>';
    exit;
}

if (empty($clients)) {
    echo '<p class="text-muted">No hay clientes activos en este momento.</p>';
    exit;
}
?>
<table class="table">
    <thead>
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Plan</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clients as $client):
            $id    = (int) ($client['id'] ?? 0);
            $code  = htmlspecialchars($client['code']      ?? '—', ENT_QUOTES, 'UTF-8');
            $name  = htmlspecialchars($client['name']      ?? '—', ENT_QUOTES, 'UTF-8');
            $email = htmlspecialchars($client['email']     ?? '—', ENT_QUOTES, 'UTF-8');
            $plan  = htmlspecialchars(ucfirst($client['plan_type'] ?? '—'), ENT_QUOTES, 'UTF-8');
        ?>
        <tr>
            <td><?= $code ?></td>
            <td><?= $name ?></td>
            <td><?= $email ?></td>
            <td><?= $plan ?></td>
            <td>
                <a href="/admin/clientes/<?= $id ?>" class="btn btn--sm btn--outline">Ver</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
