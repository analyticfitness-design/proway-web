<?php
declare(strict_types=1);

/**
 * POST /api/ai/generate-quote
 * ProWay Lab — Automated quotation generator based on project requirements.
 * Body: {
 *   "nombre": "Coach Maria",
 *   "email": "maria@example.com",
 *   "videos_por_mes": 8,
 *   "incluye_branding": true,
 *   "incluye_estrategia": true,
 *   "incluye_gestion_redes": false,
 *   "duracion_meses": 3
 * }
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/mailer.php';

requireMethod('POST');

// Rate limit: 5 per IP per hour
if (!checkRateLimit('generate_quote', 5, 3600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Limite alcanzado. Intenta de nuevo en una hora.']);
    exit;
}

$body = getJsonBody();

$nombre              = trim($body['nombre'] ?? '');
$email               = trim($body['email'] ?? '');
$videosPorMes        = (int) ($body['videos_por_mes'] ?? 0);
$incluyeBranding     = (bool) ($body['incluye_branding'] ?? false);
$incluyeEstrategia   = (bool) ($body['incluye_estrategia'] ?? false);
$incluyeGestionRedes = (bool) ($body['incluye_gestion_redes'] ?? false);
$duracionMeses       = (int) ($body['duracion_meses'] ?? 1);

// ─── Validation ───────────────────────────────────────────────────────────────

$errors = [];

if (!$nombre) {
    $errors[] = 'El campo "nombre" es obligatorio.';
}
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email no es valido.';
}
if ($videosPorMes < 1) {
    $errors[] = 'El campo "videos_por_mes" debe ser al menos 1.';
}
if ($duracionMeses < 1) {
    $errors[] = 'El campo "duracion_meses" debe ser al menos 1.';
}

if (!empty($errors)) {
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// ─── Plan Determination ───────────────────────────────────────────────────────

// Base plans
$plans = [
    'Starter'   => [
        'min_videos'         => 1,
        'max_videos'         => 4,
        'precio_mensual'     => 1200000,
        'incluye_branding'   => false,
        'incluye_estrategia' => false,
        'incluye_redes'      => false,
        'features'           => [
            'Hasta 4 videos/mes',
            'Edicion profesional',
            'Optimizacion para redes',
            'Entrega en 5 dias habiles',
        ],
    ],
    'Growth'    => [
        'min_videos'         => 5,
        'max_videos'         => 8,
        'precio_mensual'     => 1600000,
        'incluye_branding'   => true,
        'incluye_estrategia' => true,
        'incluye_redes'      => false,
        'features'           => [
            'Hasta 8 videos/mes',
            'Edicion profesional',
            'Branding basico incluido',
            'Estrategia de contenido',
            'Optimizacion para redes',
            'Entrega en 4 dias habiles',
            'Revisiones ilimitadas',
        ],
    ],
    'Authority' => [
        'min_videos'         => 9,
        'max_videos'         => 999,
        'precio_mensual'     => 2200000,
        'incluye_branding'   => true,
        'incluye_estrategia' => true,
        'incluye_redes'      => true,
        'features'           => [
            '9+ videos/mes',
            'Edicion premium',
            'Branding completo incluido',
            'Estrategia de contenido',
            'Gestion de redes sociales',
            'Calendario editorial',
            'Optimizacion para redes',
            'Entrega prioritaria (3 dias)',
            'Revisiones ilimitadas',
            'Soporte prioritario',
        ],
    ],
];

// Determine base plan
$planName = 'Starter';
foreach ($plans as $name => $plan) {
    if ($videosPorMes >= $plan['min_videos'] && $videosPorMes <= $plan['max_videos']) {
        $planName = $name;
        break;
    }
}

$plan = $plans[$planName];
$precioMensual = $plan['precio_mensual'];
$extras = 0;
$incluye = $plan['features'];
$noIncluye = [];

// ─── Extras Calculation ───────────────────────────────────────────────────────

// Branding: +$400,000 one-time if not included in plan
$brandingExtra = 0;
if ($incluyeBranding && !$plan['incluye_branding']) {
    $brandingExtra = 400000;
    $extras += $brandingExtra;
    $incluye[] = 'Branding (extra)';
} elseif (!$incluyeBranding && !$plan['incluye_branding']) {
    $noIncluye[] = 'Branding';
}

// Estrategia: included in Growth+, no extra charge specified for lower plans
// but it's a value-add that comes with higher plans
if (!$incluyeEstrategia && !$plan['incluye_estrategia']) {
    $noIncluye[] = 'Estrategia de contenido';
}

// Gestion de redes: included in Authority only, +$600,000 for lower plans
$redesExtra = 0;
if ($incluyeGestionRedes && !$plan['incluye_redes']) {
    $redesExtra = 600000;
    $precioMensual += $redesExtra;
    $incluye[] = 'Gestion de redes sociales (extra +$600.000/mes)';
} elseif (!$incluyeGestionRedes && !$plan['incluye_redes']) {
    $noIncluye[] = 'Gestion de redes sociales';
}

// ─── Discount ─────────────────────────────────────────────────────────────────

$descuentoPct = 0;
if ($duracionMeses >= 6) {
    $descuentoPct = 15;
} elseif ($duracionMeses >= 3) {
    $descuentoPct = 10;
}

$totalMensualSinDescuento = $precioMensual;
$descuentoMonto = (int) round($precioMensual * ($descuentoPct / 100));
$totalMensual = $precioMensual - $descuentoMonto;

// One-time extras are NOT discounted, added to first month or spread
$totalProyecto = ($totalMensual * $duracionMeses) + $extras;

// ─── Update features with actual video count ─────────────────────────────────

// Replace generic video count with actual
$incluye = array_map(function (string $f) use ($videosPorMes): string {
    if (preg_match('/^(Hasta \d+|9\+) videos\/mes$/', $f)) {
        return $videosPorMes . ' videos/mes';
    }
    return $f;
}, $incluye);

// ─── Build Quote Response ─────────────────────────────────────────────────────

$quote = [
    'plan_recomendado'    => $planName,
    'precio_mensual_cop'  => $totalMensualSinDescuento,
    'extras_cop'          => $extras,
    'descuento_pct'       => $descuentoPct,
    'total_mensual_cop'   => $totalMensual,
    'total_proyecto_cop'  => $totalProyecto,
    'duracion_meses'      => $duracionMeses,
    'incluye'             => array_values($incluye),
    'no_incluye'          => array_values($noIncluye),
];

if ($extras > 0) {
    $quote['nota_extras'] = 'Los extras one-time ($' . number_format($extras, 0, ',', '.') . ' COP) se suman al total del proyecto.';
}

// ─── Send notification to admin (fire-and-forget) ─────────────────────────────

try {
    $bodyHtml = '<p style="margin:0 0 12px 0">Nueva cotizacion generada:</p>
        <table cellpadding="4" cellspacing="0" style="font-size:13px;color:#e0e0e0;font-family:monospace">
        <tr><td style="color:#A1A1AA">Cliente</td><td>' . htmlspecialchars($nombre, ENT_QUOTES) . '</td></tr>
        <tr><td style="color:#A1A1AA">Email</td><td>' . htmlspecialchars($email ?: '(no proporcionado)', ENT_QUOTES) . '</td></tr>
        <tr><td style="color:#A1A1AA">Plan</td><td style="color:#00D9FF;font-weight:700">' . htmlspecialchars($planName, ENT_QUOTES) . '</td></tr>
        <tr><td style="color:#A1A1AA">Videos/mes</td><td>' . $videosPorMes . '</td></tr>
        <tr><td style="color:#A1A1AA">Duracion</td><td>' . $duracionMeses . ' meses</td></tr>
        <tr><td style="color:#A1A1AA">Mensual</td><td>$' . number_format($totalMensual, 0, ',', '.') . ' COP</td></tr>
        <tr><td style="color:#A1A1AA">Extras</td><td>$' . number_format($extras, 0, ',', '.') . ' COP</td></tr>
        <tr><td style="color:#A1A1AA">Descuento</td><td>' . $descuentoPct . '%</td></tr>
        <tr><td style="color:#A1A1AA">Total proyecto</td><td style="color:#00FF87;font-weight:700">$' . number_format($totalProyecto, 0, ',', '.') . ' COP</td></tr>
        </table>';

    if (!empty($noIncluye)) {
        $bodyHtml .= '<p style="margin:12px 0 4px 0;color:#A1A1AA;font-size:12px">No incluye: ' . htmlspecialchars(implode(', ', $noIncluye), ENT_QUOTES) . '</p>';
    }

    $htmlEmail = buildEmailHtml('NUEVA COTIZACION', $bodyHtml, 'Ver en Admin', 'https://prowaylab.com/admin.html');
    @sendEmail(PW_ADMIN_EMAIL, 'Cotizacion: ' . $nombre . ' — ' . $planName . ' — $' . number_format($totalProyecto, 0, ',', '.'), $htmlEmail);
    if (PW_ADMIN_CC) {
        @sendEmail(PW_ADMIN_CC, 'Cotizacion: ' . $nombre . ' — ' . $planName . ' — $' . number_format($totalProyecto, 0, ',', '.'), $htmlEmail);
    }
} catch (\Throwable $e) {
    // Fire-and-forget: don't block the response
    error_log('[ProWay Quote] Email error: ' . $e->getMessage());
}

// ─── Response ─────────────────────────────────────────────────────────────────

echo json_encode([
    'ok'    => true,
    'quote' => $quote,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
