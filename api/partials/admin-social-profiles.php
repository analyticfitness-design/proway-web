<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

use ProWay\Domain\SocialMetrics\MySQLSocialProfileRepository;
use ProWay\Domain\SocialMetrics\MySQLSocialPostRepository;
use ProWay\Domain\SocialMetrics\MySQLMetricsRepository;
use ProWay\Domain\SocialMetrics\SocialMetricsService;
use ProWay\Infrastructure\Database\Connection;

header('Content-Type: text/html; charset=utf-8');

if ($currentUser->type !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert--error">Acceso denegado.</div>';
    exit;
}

$pdo = Connection::getInstance();
$socialService = new SocialMetricsService(
    new MySQLSocialProfileRepository($pdo),
    new MySQLSocialPostRepository($pdo),
    new MySQLMetricsRepository($pdo),
);

try {
    $profiles = $socialService->getAllActiveProfiles();
    $clients  = $clientService->listAll();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar perfiles sociales. Intentalo de nuevo.</div>';
    exit;
}

// Group profiles by client
$grouped = [];
foreach ($profiles as $p) {
    $clientKey = ($p['client_name'] ?? $p['client_code'] ?? 'Sin cliente') . '||' . ($p['client_id'] ?? 0);
    $grouped[$clientKey][] = $p;
}

$platformIcons = [
    'instagram' => 'fab fa-instagram',
    'tiktok'    => 'fab fa-tiktok',
];

$platformColors = [
    'instagram' => '#E1306C',
    'tiktok'    => '#00F2EA',
];
?>

<!-- Add Profile Form -->
<div class="card" style="padding: var(--pw-space-4); margin-bottom: var(--pw-space-4);">
    <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">Agregar Perfil Social</h3>
    <form id="addSocialProfileForm" style="display: flex; gap: var(--pw-space-3); flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 180px;">
            <label class="form-label">Cliente</label>
            <select name="client_id" class="form-input" required>
                <option value="">Seleccionar cliente...</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= (int) $c['id'] ?>">
                    <?= htmlspecialchars($c['nombre'] ?? $c['name'] ?? $c['code'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 140px;">
            <label class="form-label">Plataforma</label>
            <select name="platform" class="form-input" required>
                <option value="instagram">Instagram</option>
                <option value="tiktok">TikTok</option>
            </select>
        </div>
        <div style="flex: 1; min-width: 180px;">
            <label class="form-label">Usuario</label>
            <input type="text" name="username" class="form-input" placeholder="@usuario" required>
        </div>
        <div>
            <button type="submit" class="btn btn--primary">
                <i class="fas fa-plus"></i> Agregar
            </button>
        </div>
    </form>
</div>

<?php if (empty($profiles)): ?>
    <p class="text-muted" style="padding: var(--pw-space-4);">No hay perfiles sociales registrados.</p>
<?php else: ?>

<!-- Profiles Grouped by Client -->
<?php foreach ($grouped as $clientKey => $clientProfiles):
    [$clientName, $clientId] = explode('||', $clientKey);
?>
<div class="card" style="padding: var(--pw-space-4); margin-bottom: var(--pw-space-3);">
    <h4 class="card__title" style="margin-bottom: var(--pw-space-3);">
        <i class="fas fa-user"></i>
        <?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?>
        <span class="badge badge--neutral" style="margin-left: var(--pw-space-2);">
            <?= count($clientProfiles) ?> perfil<?= count($clientProfiles) !== 1 ? 'es' : '' ?>
        </span>
    </h4>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Plataforma</th>
                    <th>Usuario</th>
                    <th>Seguidores</th>
                    <th>Posts</th>
                    <th>Ultima Sincronizacion</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientProfiles as $prof):
                    $platform  = $prof['platform'];
                    $icon      = $platformIcons[$platform] ?? 'fas fa-globe';
                    $color     = $platformColors[$platform] ?? '#A1A1AA';
                    $synced    = $prof['last_synced_at']
                        ? date('d/m/Y H:i', strtotime($prof['last_synced_at']))
                        : 'Nunca';
                ?>
                <tr>
                    <td>
                        <i class="<?= $icon ?>" style="color: <?= $color ?>; margin-right: 6px;"></i>
                        <?= ucfirst($platform) ?>
                    </td>
                    <td>
                        <strong>@<?= htmlspecialchars($prof['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if (!empty($prof['display_name'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($prof['display_name'], ENT_QUOTES, 'UTF-8') ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format((int) $prof['followers']) ?></td>
                    <td><?= number_format((int) $prof['posts_count']) ?></td>
                    <td>
                        <small class="text-muted"><?= $synced ?></small>
                    </td>
                    <td>
                        <button class="btn btn--small btn--danger"
                                onclick="deleteSocialProfile(<?= (int) $prof['id'] ?>)"
                                title="Eliminar perfil">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<script>
(function() {
    const form = document.getElementById('addSocialProfileForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = {
                client_id: form.client_id.value,
                platform:  form.platform.value,
                username:  form.username.value,
            };

            try {
                const res = await fetch('/api/v1/admin/social-profiles', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(data),
                });
                const json = await res.json();
                if (json.success) {
                    // Reload the partial via HTMX or fallback
                    if (window.htmx) {
                        htmx.trigger(document.body, 'socialProfileAdded');
                    }
                    location.reload();
                } else {
                    alert(json.error?.message || 'Error al agregar perfil');
                }
            } catch (err) {
                alert('Error de conexion');
            }
        });
    }

    window.deleteSocialProfile = async function(id) {
        if (!confirm('Eliminar este perfil social?')) return;

        try {
            const res = await fetch('/api/v1/admin/social-profiles/' + id, {
                method: 'DELETE',
                credentials: 'include',
            });
            const json = await res.json();
            if (json.success) {
                location.reload();
            } else {
                alert(json.error?.message || 'Error al eliminar');
            }
        } catch (err) {
            alert('Error de conexion');
        }
    };
})();
</script>
