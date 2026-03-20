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

// ── Handle POST: update WhatsApp phone / toggle ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $clientId       = (int) ($_POST['client_id'] ?? 0);
    $waPhone        = trim((string) ($_POST['wa_phone'] ?? ''));
    $waNotifications = isset($_POST['wa_notifications']) ? (int) $_POST['wa_notifications'] : 1;

    if ($clientId === 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'client_id requerido']);
        exit;
    }

    try {
        $clientService->update($clientId, [
            'wa_phone'        => $waPhone !== '' ? $waPhone : null,
            'wa_notifications' => $waNotifications,
        ]);
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al actualizar']);
    }
    exit;
}

// ── GET: render the config table ───────────────────────────────────────────
try {
    $clients = $clientService->getActiveClients();
} catch (\Throwable) {
    echo '<div class="alert alert--error">Error al cargar los clientes.</div>';
    exit;
}

$waConfigured = defined('WA_PHONE_NUMBER_ID') && WA_PHONE_NUMBER_ID !== '';
?>

<?php if (!$waConfigured): ?>
<div class="alert alert--warning" style="margin-bottom: 1rem;">
    <strong>WhatsApp API no configurada.</strong> Define <code>WA_PHONE_NUMBER_ID</code> y <code>WA_ACCESS_TOKEN</code> en las variables de entorno para activar el envio de mensajes.
</div>
<?php endif; ?>

<div class="card" style="padding: 1.5rem;">
    <h3 style="margin-top: 0;">Configuracion WhatsApp por Cliente</h3>

    <?php if (empty($clients)): ?>
        <p class="text-muted">No hay clientes activos.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Email</th>
                <th>WhatsApp</th>
                <th>Notificaciones</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $client):
            $id      = (int) ($client['id'] ?? 0);
            $name    = htmlspecialchars($client['name'] ?? '—', ENT_QUOTES, 'UTF-8');
            $email   = htmlspecialchars($client['email'] ?? '—', ENT_QUOTES, 'UTF-8');
            $waPhone = htmlspecialchars($client['wa_phone'] ?? '', ENT_QUOTES, 'UTF-8');
            $waOn    = (int) ($client['wa_notifications'] ?? 1);
        ?>
        <tr id="wa-row-<?= $id ?>">
            <td><strong><?= $name ?></strong></td>
            <td><?= $email ?></td>
            <td>
                <input
                    type="text"
                    class="input input--sm"
                    id="wa-phone-<?= $id ?>"
                    value="<?= $waPhone ?>"
                    placeholder="+573001234567"
                    style="width: 170px;"
                >
            </td>
            <td>
                <label class="toggle" style="cursor: pointer;">
                    <input
                        type="checkbox"
                        id="wa-toggle-<?= $id ?>"
                        <?= $waOn ? 'checked' : '' ?>
                    >
                    <span id="wa-label-<?= $id ?>"><?= $waOn ? 'Activo' : 'Inactivo' ?></span>
                </label>
            </td>
            <td>
                <button
                    class="btn btn--sm btn--primary"
                    onclick="saveWaConfig(<?= $id ?>)"
                    id="wa-btn-<?= $id ?>"
                >
                    Guardar
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
async function saveWaConfig(clientId) {
    const phone  = document.getElementById('wa-phone-' + clientId).value.trim();
    const toggle = document.getElementById('wa-toggle-' + clientId);
    const btn    = document.getElementById('wa-btn-' + clientId);
    const label  = document.getElementById('wa-label-' + clientId);

    const enabled = toggle.checked ? 1 : 0;

    btn.disabled  = true;
    btn.textContent = 'Guardando...';

    const form = new FormData();
    form.append('client_id', clientId);
    form.append('wa_phone', phone);
    form.append('wa_notifications', enabled);

    try {
        const res = await fetch('/api/partials/admin-whatsapp-config.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
        });

        const data = await res.json();

        if (data.ok) {
            btn.textContent = 'Guardado';
            label.textContent = enabled ? 'Activo' : 'Inactivo';
            setTimeout(() => { btn.textContent = 'Guardar'; btn.disabled = false; }, 1500);
        } else {
            btn.textContent = 'Error';
            setTimeout(() => { btn.textContent = 'Guardar'; btn.disabled = false; }, 2000);
        }
    } catch {
        btn.textContent = 'Error';
        setTimeout(() => { btn.textContent = 'Guardar'; btn.disabled = false; }, 2000);
    }
}
</script>
