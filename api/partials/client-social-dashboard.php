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

$pdo = Connection::getInstance();
$socialService = new SocialMetricsService(
    new MySQLSocialProfileRepository($pdo),
    new MySQLSocialPostRepository($pdo),
    new MySQLMetricsRepository($pdo),
);

try {
    $profiles = $socialService->getClientProfiles($currentUser->id);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar metricas sociales. Intentalo de nuevo.</div>';
    exit;
}

if (empty($profiles)) {
    echo '<div style="text-align:center; padding: var(--pw-space-6);">';
    echo '  <i class="fas fa-chart-line" style="font-size:3rem; color: var(--pw-gray-500); margin-bottom: var(--pw-space-3); display:block;"></i>';
    echo '  <p class="text-muted">No tienes perfiles sociales vinculados aun.</p>';
    echo '  <p class="text-muted"><small>Contacta a tu gestor de cuenta para activar el monitoreo de tus redes.</small></p>';
    echo '</div>';
    exit;
}

$platformIcons = [
    'instagram' => 'fab fa-instagram',
    'tiktok'    => 'fab fa-tiktok',
];
$platformColors = [
    'instagram' => '#E1306C',
    'tiktok'    => '#00F2EA',
];

// Build dashboard data for each profile
$dashboards = [];
$comparisons = [];
foreach ($profiles as $profile) {
    $pid = (int) $profile['id'];
    $dashboards[$pid]  = $socialService->getProfileDashboard($pid, 30);
    $comparisons[$pid] = $socialService->getProWayComparison($pid);
}
?>

<!-- Profile Cards Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--pw-space-4); margin-bottom: var(--pw-space-4);">
    <?php foreach ($profiles as $profile):
        $pid      = (int) $profile['id'];
        $platform = $profile['platform'];
        $icon     = $platformIcons[$platform] ?? 'fas fa-globe';
        $color    = $platformColors[$platform] ?? '#A1A1AA';
        $growth   = $dashboards[$pid]['growth'] ?? [];
        $growthPct = $growth['growth_pct'] ?? 0;
        $growthSign = $growthPct >= 0 ? '+' : '';
        $growthColor = $growthPct >= 0 ? '#00FF87' : '#E31E24';
    ?>
    <div class="card" style="padding: var(--pw-space-4);">
        <div style="display: flex; align-items: center; gap: var(--pw-space-3); margin-bottom: var(--pw-space-3);">
            <?php if (!empty($profile['profile_pic_url'])): ?>
            <img src="<?= htmlspecialchars($profile['profile_pic_url'], ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?>"
                 style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
            <?php endif; ?>
            <div>
                <h4 style="margin: 0;">
                    <i class="<?= $icon ?>" style="color: <?= $color ?>; margin-right: 4px;"></i>
                    @<?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?>
                </h4>
                <?php if (!empty($profile['display_name'])): ?>
                <small class="text-muted"><?= htmlspecialchars($profile['display_name'], ENT_QUOTES, 'UTF-8') ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--pw-space-2); text-align: center;">
            <div>
                <div style="font-size: 1.3rem; font-weight: 700;"><?= number_format((int) $profile['followers']) ?></div>
                <small class="text-muted">Seguidores</small>
                <?php if ($growthPct != 0): ?>
                <div style="font-size: .75rem; color: <?= $growthColor ?>;">
                    <?= $growthSign ?><?= $growthPct ?>%
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size: 1.3rem; font-weight: 700;"><?= number_format((int) $profile['following']) ?></div>
                <small class="text-muted">Siguiendo</small>
            </div>
            <div>
                <div style="font-size: 1.3rem; font-weight: 700;"><?= number_format((int) $profile['posts_count']) ?></div>
                <small class="text-muted">Posts</small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Engagement Rate Chart Section -->
<?php foreach ($profiles as $profile):
    $pid       = (int) $profile['id'];
    $platform  = $profile['platform'];
    $icon      = $platformIcons[$platform] ?? 'fas fa-globe';
    $color     = $platformColors[$platform] ?? '#A1A1AA';
    $timeline  = $dashboards[$pid]['timeline'] ?? [];
    $growth    = $dashboards[$pid]['growth'] ?? [];

    // Prepare chart data as JSON (data-attributes for Chart.js)
    $chartLabels = json_encode(array_map(fn($t) => $t['date'] ?? '', $timeline));
    $chartFollowers = json_encode(array_map(fn($t) => (int) ($t['followers'] ?? 0), $timeline));
    $chartEngagement = json_encode(array_map(fn($t) => round((float) ($t['engagement_rate'] ?? 0), 2), $timeline));
    $chartLikes = json_encode(array_map(fn($t) => (int) ($t['likes'] ?? 0), $timeline));
    $chartViews = json_encode(array_map(fn($t) => (int) ($t['views'] ?? 0), $timeline));
?>
<div class="card" style="padding: var(--pw-space-4); margin-bottom: var(--pw-space-4);">
    <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">
        <i class="<?= $icon ?>" style="color: <?= $color ?>;"></i>
        @<?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?> — Metricas (30 dias)
    </h3>

    <!-- Growth Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: var(--pw-space-3); margin-bottom: var(--pw-space-4);">
        <div class="stat-card">
            <div class="stat-card__value"><?= number_format((int) ($growth['total_likes'] ?? 0)) ?></div>
            <div class="stat-card__label">Likes Totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__value"><?= number_format((int) ($growth['total_comments'] ?? 0)) ?></div>
            <div class="stat-card__label">Comentarios</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__value"><?= number_format((int) ($growth['total_views'] ?? 0)) ?></div>
            <div class="stat-card__label">Vistas</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__value"><?= number_format((float) ($growth['avg_engagement'] ?? 0), 2) ?>%</div>
            <div class="stat-card__label">Engagement Prom.</div>
        </div>
    </div>

    <!-- Chart container with data-attributes for Chart.js -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--pw-space-4);">
        <div>
            <canvas class="social-chart-followers"
                    height="250"
                    style="max-height: 250px;"
                    data-labels="<?= htmlspecialchars($chartLabels, ENT_QUOTES, 'UTF-8') ?>"
                    data-values="<?= htmlspecialchars($chartFollowers, ENT_QUOTES, 'UTF-8') ?>"
                    data-color="<?= $color ?>">
            </canvas>
            <p style="text-align: center; margin-top: var(--pw-space-2);"><small class="text-muted">Seguidores</small></p>
        </div>
        <div>
            <canvas class="social-chart-engagement"
                    height="250"
                    style="max-height: 250px;"
                    data-labels="<?= htmlspecialchars($chartLabels, ENT_QUOTES, 'UTF-8') ?>"
                    data-values="<?= htmlspecialchars($chartEngagement, ENT_QUOTES, 'UTF-8') ?>"
                    data-color="<?= $color ?>">
            </canvas>
            <p style="text-align: center; margin-top: var(--pw-space-2);"><small class="text-muted">Tasa de Engagement (%)</small></p>
        </div>
    </div>
</div>

<!-- Recent Posts Grid -->
<?php
    $posts = $dashboards[$pid]['posts'] ?? [];
    if (!empty($posts)):
?>
<div class="card" style="padding: var(--pw-space-4); margin-bottom: var(--pw-space-4);">
    <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">
        Publicaciones Recientes — @<?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?>
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--pw-space-3);">
        <?php foreach ($posts as $post):
            $postType  = $post['post_type'] ?? 'post';
            $typeLabel = ['reel' => 'Reel', 'post' => 'Post', 'story' => 'Story', 'video' => 'Video', 'carousel' => 'Carousel'][$postType] ?? ucfirst($postType);
            $isProWay  = (bool) ($post['is_proway'] ?? false);
        ?>
        <div style="background: rgba(255,255,255,0.03); border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.06);">
            <?php if (!empty($post['thumbnail_url'])): ?>
            <div style="position: relative;">
                <img src="<?= htmlspecialchars($post['thumbnail_url'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="Post"
                     loading="lazy"
                     style="width: 100%; aspect-ratio: 1; object-fit: cover; display: block;">
                <span style="position: absolute; top: 6px; left: 6px; background: rgba(0,0,0,0.7); color: #fff; font-size: .65rem; padding: 2px 6px; border-radius: 4px;">
                    <?= $typeLabel ?>
                </span>
                <?php if ($isProWay): ?>
                <span style="position: absolute; top: 6px; right: 6px; background: #00D9FF; color: #000; font-size: .6rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">
                    ProWay
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div style="padding: 8px;">
                <div style="display: flex; justify-content: space-between; font-size: .75rem; color: var(--pw-gray-400);">
                    <span title="Likes"><i class="fas fa-heart"></i> <?= number_format((int) ($post['total_likes'] ?? 0)) ?></span>
                    <span title="Comentarios"><i class="fas fa-comment"></i> <?= number_format((int) ($post['total_comments'] ?? 0)) ?></span>
                    <span title="Vistas"><i class="fas fa-eye"></i> <?= number_format((int) ($post['total_views'] ?? 0)) ?></span>
                </div>
                <?php if (!empty($post['posted_at'])): ?>
                <div style="font-size: .65rem; color: var(--pw-gray-500); margin-top: 4px;">
                    <?= date('d/m/Y', strtotime($post['posted_at'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ProWay vs Organic Comparison -->
<?php
    $comp = $comparisons[$pid] ?? [];
    $proWay  = $comp['proway'] ?? ['count' => 0, 'averages' => []];
    $organic = $comp['organic'] ?? ['count' => 0, 'averages' => []];

    if ($proWay['count'] > 0 || $organic['count'] > 0):
        $proWayAvg  = $proWay['averages'] ?? [];
        $organicAvg = $organic['averages'] ?? [];

        // Data for comparison bar chart
        $compLabels = json_encode(['Likes', 'Comentarios', 'Vistas', 'Shares']);
        $compProWay = json_encode([
            (int) ($proWayAvg['avg_likes'] ?? 0),
            (int) ($proWayAvg['avg_comments'] ?? 0),
            (int) ($proWayAvg['avg_views'] ?? 0),
            (int) ($proWayAvg['avg_shares'] ?? 0),
        ]);
        $compOrganic = json_encode([
            (int) ($organicAvg['avg_likes'] ?? 0),
            (int) ($organicAvg['avg_comments'] ?? 0),
            (int) ($organicAvg['avg_views'] ?? 0),
            (int) ($organicAvg['avg_shares'] ?? 0),
        ]);
?>
<div class="card" style="padding: var(--pw-space-4); margin-bottom: var(--pw-space-4);">
    <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">
        ProWay vs Organico — @<?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?>
    </h3>
    <p class="text-muted" style="margin-bottom: var(--pw-space-3);">
        Comparacion de rendimiento entre contenido producido por ProWay Lab
        (<?= $proWay['count'] ?> posts) y contenido organico (<?= $organic['count'] ?> posts).
    </p>
    <canvas class="social-chart-comparison"
            height="280"
            style="max-height: 280px;"
            data-labels="<?= htmlspecialchars($compLabels, ENT_QUOTES, 'UTF-8') ?>"
            data-proway="<?= htmlspecialchars($compProWay, ENT_QUOTES, 'UTF-8') ?>"
            data-organic="<?= htmlspecialchars($compOrganic, ENT_QUOTES, 'UTF-8') ?>">
    </canvas>
</div>
<?php endif; ?>

<?php endforeach; ?>

<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
    const CYAN  = '#00D9FF';
    const GREEN = '#00FF87';
    const GRAY  = '#A1A1AA';
    const DARK_GRID = 'rgba(161,161,170,0.15)';

    const commonScaleOpts = {
        x: { ticks: { color: GRAY }, grid: { color: DARK_GRID } },
        y: { ticks: { color: GRAY }, grid: { color: DARK_GRID } }
    };

    // ── Followers line charts ───────────────────────────────
    document.querySelectorAll('.social-chart-followers').forEach(function(canvas) {
        var labels = JSON.parse(canvas.dataset.labels || '[]');
        var values = JSON.parse(canvas.dataset.values || '[]');
        var color  = canvas.dataset.color || CYAN;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Seguidores',
                    data: values,
                    borderColor: color,
                    backgroundColor: color + '1A',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: GRAY } } },
                scales: commonScaleOpts,
            }
        });
    });

    // ── Engagement line charts ──────────────────────────────
    document.querySelectorAll('.social-chart-engagement').forEach(function(canvas) {
        var labels = JSON.parse(canvas.dataset.labels || '[]');
        var values = JSON.parse(canvas.dataset.values || '[]');
        var color  = canvas.dataset.color || GREEN;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Engagement %',
                    data: values,
                    borderColor: GREEN,
                    backgroundColor: GREEN + '1A',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: GRAY } } },
                scales: {
                    x: { ticks: { color: GRAY }, grid: { color: DARK_GRID } },
                    y: {
                        ticks: {
                            color: GRAY,
                            callback: function(v) { return v + '%'; }
                        },
                        grid: { color: DARK_GRID }
                    }
                },
            }
        });
    });

    // ── ProWay vs Organic comparison charts ──────────────────
    document.querySelectorAll('.social-chart-comparison').forEach(function(canvas) {
        var labels  = JSON.parse(canvas.dataset.labels || '[]');
        var proway  = JSON.parse(canvas.dataset.proway || '[]');
        var organic = JSON.parse(canvas.dataset.organic || '[]');

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'ProWay Lab',
                        data: proway,
                        backgroundColor: CYAN,
                        borderRadius: 4,
                    },
                    {
                        label: 'Organico',
                        data: organic,
                        backgroundColor: GRAY,
                        borderRadius: 4,
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: GRAY, padding: 12 }
                    }
                },
                scales: commonScaleOpts,
            }
        });
    });
})();
</script>
