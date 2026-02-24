<?php
declare(strict_types=1);

/**
 * POST /api/ai/generate-script
 * ProWay Lab — Template-based video script generator for fitness reels.
 * Body: { "tema": "...", "duracion": "30s", "estilo": "educativo", "publico": "..." }
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';

requireMethod('POST');

// Rate limit: 10 per IP per hour
if (!checkRateLimit('generate_script', 10, 3600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Limite alcanzado. Intenta de nuevo en una hora.']);
    exit;
}

$body     = getJsonBody();
$tema     = trim($body['tema'] ?? '');
$duracion = trim($body['duracion'] ?? '30s');
$estilo   = trim($body['estilo'] ?? 'educativo');
$publico  = trim($body['publico'] ?? 'coaches fitness LATAM');

if (!$tema) {
    echo json_encode(['ok' => false, 'error' => 'El campo "tema" es obligatorio.']);
    exit;
}

// ─── Hook Templates ───────────────────────────────────────────────────────────

$hookTemplates = [
    'pregunta_provocativa' => [
        '¿Sabias que {dato_tema}? La mayoria de coaches ignoran esto completamente.',
        '¿Sabias que el 80% de tus clientes estan haciendo mal {tema_corto}? Esto lo cambia todo.',
        '¿Por que nadie habla de {tema_corto}? Te lo explico en {duracion}.',
    ],
    'dato_sorprendente' => [
        'Un estudio reciente demostro que {dato_tema}. Y casi nadie lo aplica.',
        'El 90% de los coaches no saben esto sobre {tema_corto}. Los datos no mienten.',
        'Solo el 3% de los profesionales fitness aplican esto sobre {tema_corto}. Aqui esta la ciencia.',
    ],
    'error_comun' => [
        'El error #1 que cometen los coaches con {tema_corto}: esto te va a sorprender.',
        'Si haces esto con {tema_corto}, estas saboteando a tus clientes sin saberlo.',
        'Deja de hacer esto con {tema_corto}. Es el error mas comun y tiene solucion.',
    ],
    'contra_intuitivo' => [
        'Todo el mundo dice que {tema_corto} funciona asi. Pero la evidencia dice lo contrario.',
        'Lo que siempre te dijeron sobre {tema_corto} esta mal. Aqui va la realidad.',
        'Suena loco, pero {tema_corto} no funciona como crees. Te explico por que.',
    ],
    'urgencia' => [
        'Si no haces esto con {tema_corto} antes de entrenar, estas perdiendo resultados.',
        'Tienes que saber esto sobre {tema_corto} ANTES de tu proxima sesion.',
        'Esto sobre {tema_corto} puede cambiar los resultados de tus clientes desde manana.',
    ],
    'autoridad' => [
        'Despues de trabajar con cientos de clientes, esto es lo que se sobre {tema_corto}.',
        'Llevo anos aplicando esto con {tema_corto} y los resultados hablan solos.',
        'Lo que aprendi sobre {tema_corto} despues de miles de horas de practica.',
    ],
];

// ─── Development Templates (by estilo) ───────────────────────────────────────

$developmentTemplates = [
    'educativo' => [
        [
            'Primero, entendamos la base: {tema_corto} depende de factores que la mayoria ignora.',
            'Segundo, la evidencia muestra que el enfoque correcto es mas simple de lo que parece.',
            'Tercero, aplicalo asi: empieza con lo basico y ajusta segun la respuesta individual.',
            'Por ultimo, mide los resultados. Sin datos, solo estas adivinando.',
        ],
        [
            'El concepto clave es este: {tema_corto} no es blanco o negro.',
            'Lo que funciona es la personalizacion. Cada cliente tiene necesidades distintas.',
            'La ciencia respalda un enfoque progresivo y basado en adherencia.',
            'Recuerda: la mejor estrategia es la que tu cliente puede mantener.',
        ],
        [
            'Vamos con los fundamentos: {tema_corto} se basa en principios claros.',
            'El error esta en complicarlo. La simplicidad gana a largo plazo.',
            'Punto clave: la consistencia supera a la perfeccion cada vez.',
        ],
    ],
    'motivacional' => [
        [
            'Escucha bien: {tema_corto} no es cuestion de suerte, es cuestion de sistema.',
            'Los que obtienen resultados tienen algo en comun: ejecutan sin excusas.',
            'Tu decides: seguir repitiendo lo mismo o aplicar lo que realmente funciona.',
            'El cambio empieza HOY. No manana, no el lunes. Ahora.',
        ],
        [
            'Todos los coaches exitosos que conozco hacen esto con {tema_corto}.',
            'No se trata de talento. Se trata de disciplina y proceso.',
            'La diferencia entre un coach promedio y uno elite? La ejecucion constante.',
        ],
    ],
    'tutorial' => [
        [
            'Paso 1: Evalua donde estas con {tema_corto}. Se honesto.',
            'Paso 2: Define un protocolo claro. Sin protocolo, no hay progreso medible.',
            'Paso 3: Implementa durante minimo 4 semanas antes de cambiar nada.',
            'Paso 4: Revisa los datos y ajusta. Repite el ciclo.',
        ],
        [
            'Primero: identifica el punto de partida de tu cliente respecto a {tema_corto}.',
            'Segundo: elige una estrategia basada en evidencia, no en tendencias.',
            'Tercero: haz seguimiento semanal. Los numeros no mienten.',
        ],
    ],
    'controversial' => [
        [
            'Esto va a molestar a muchos, pero {tema_corto} no funciona como lo ensenan.',
            'La industria fitness vende humo en este tema. Aqui van los datos reales.',
            'No digo que sea facil aceptarlo, pero la evidencia es clara.',
            'Aplica esto y deja que los resultados hablen por ti.',
        ],
        [
            'Si, ya se que esto es polemico. Pero alguien tiene que decirlo sobre {tema_corto}.',
            'Los "expertos" que repiten esto estan ignorando la investigacion mas reciente.',
            'La verdad: funciona distinto de lo que siempre te vendieron.',
        ],
    ],
];

// ─── CTA Templates ────────────────────────────────────────────────────────────

$ctaTemplates = [
    'Guarda este reel y compartelo con un coach que necesite escuchar esto.',
    'Sigueme para mas contenido basado en ciencia sobre fitness.',
    'Comenta "GUIA" y te envio el recurso completo sobre {tema_corto}.',
    '¿Quieres profundizar en {tema_corto}? Link en mi bio.',
    'Dale like si aprendiste algo nuevo. Sigueme para contenido diario.',
    'Compartelo con alguien que necesita saber esto. Y sigueme para mas.',
    'Etiqueta a un coach que tiene que ver esto. Nos vemos en el siguiente.',
    '¿Te suena? Dejame en comentarios tu experiencia con {tema_corto}.',
];

// ─── Hashtag Pools ────────────────────────────────────────────────────────────

$hashtagPools = [
    'general'        => ['#fitness', '#coach', '#coachfitness', '#entrenamiento', '#saludable', '#fitnessmotivation', '#entrenamientopersonal'],
    'nutricion'      => ['#nutricion', '#nutriciondeportiva', '#alimentacionsaludable', '#dieta', '#macros', '#proteina', '#mealprep', '#comidasaludable'],
    'entrenamiento'  => ['#entrenamiento', '#entreno', '#fuerza', '#hipertrofia', '#musculacion', '#gym', '#workout', '#training'],
    'mindset'        => ['#mindset', '#mentalidadganadora', '#disciplina', '#habitos', '#crecimientopersonal', '#motivacion'],
    'negocio'        => ['#negociofitness', '#coachonline', '#emprendimientofitness', '#marcapersonal', '#clientesonline'],
    'tendencias'     => ['#tendencias', '#viral', '#fyp', '#parati', '#trending', '#reels', '#reelsfitness'],
];

// ─── Generator Logic ──────────────────────────────────────────────────────────

/**
 * Derive a short topic label from the tema input.
 */
function extractTemaCort(string $tema): string {
    // Take first 40 chars, clean up
    $short = mb_substr($tema, 0, 40, 'UTF-8');
    $short = rtrim($short, ' .,;:');
    return mb_strtolower($short, 'UTF-8');
}

/**
 * Detect which hashtag categories are relevant to the topic.
 */
function detectCategories(string $tema): array {
    $tema = mb_strtolower($tema, 'UTF-8');
    $cats = ['general']; // always include general

    $map = [
        'nutricion'     => ['nutricion', 'nutrient', 'dieta', 'comida', 'aliment', 'proteina', 'macro', 'caloria', 'carbohidrato', 'grasa', 'meal', 'comer', 'suplemento', 'creatina', 'whey', 'pre-entreno', 'post-entreno'],
        'entrenamiento' => ['entrena', 'ejercicio', 'fuerza', 'hipertrofia', 'musculo', 'sentadilla', 'press', 'peso', 'repeticion', 'serie', 'volumen', 'intensidad', 'rutina', 'split', 'cardio', 'HIIT'],
        'mindset'       => ['mindset', 'mental', 'motivacion', 'habito', 'disciplina', 'psicolog', 'mentalidad', 'actitud', 'constancia'],
        'negocio'       => ['negocio', 'cliente', 'venta', 'marca', 'precio', 'cobrar', 'online', 'redes', 'instagram', 'contenido', 'marketing', 'emprender'],
        'tendencias'    => ['tendencia', 'viral', 'trend', 'moda', 'nuevo', 'reel'],
    ];

    foreach ($map as $cat => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($tema, $kw) !== false) {
                $cats[] = $cat;
                break;
            }
        }
    }

    return array_unique($cats);
}

/**
 * Pick a random element from an array.
 */
function pick(array $arr): mixed {
    return $arr[array_rand($arr)];
}

/**
 * Replace placeholders in a string.
 */
function fillPlaceholders(string $text, array $replacements): string {
    foreach ($replacements as $key => $value) {
        $text = str_replace('{' . $key . '}', $value, $text);
    }
    return $text;
}

// ─── Build Script ─────────────────────────────────────────────────────────────

$temaCorto = extractTemaCort($tema);
$categories = detectCategories($tema);

$replacements = [
    'tema_corto'  => $temaCorto,
    'dato_tema'   => $temaCorto . ' tiene un impacto directo en el rendimiento',
    'duracion'    => $duracion,
    'publico'     => $publico,
];

// 1) Pick a hook
$hookType = pick(array_keys($hookTemplates));
$hookText = fillPlaceholders(pick($hookTemplates[$hookType]), $replacements);

// 2) Pick development based on estilo (fallback to educativo)
$estiloKey = array_key_exists($estilo, $developmentTemplates) ? $estilo : 'educativo';
$devPoints = pick($developmentTemplates[$estiloKey]);
$devPoints = array_map(fn(string $p) => fillPlaceholders($p, $replacements), $devPoints);
$desarrollo = implode("\n", array_map(fn(string $p, int $i) => ($i + 1) . '. ' . $p, $devPoints, array_keys($devPoints)));

// 3) Pick CTA
$ctaText = fillPlaceholders(pick($ctaTemplates), $replacements);

// 4) Build hashtags (5-8)
$hashtags = [];
foreach ($categories as $cat) {
    if (isset($hashtagPools[$cat])) {
        $pool = $hashtagPools[$cat];
        shuffle($pool);
        $take = min(3, count($pool));
        $hashtags = array_merge($hashtags, array_slice($pool, 0, $take));
    }
}
$hashtags = array_unique($hashtags);
shuffle($hashtags);
$hashtags = array_slice($hashtags, 0, rand(5, 8));

// ─── Response ─────────────────────────────────────────────────────────────────

echo json_encode([
    'ok'     => true,
    'script' => [
        'hook'               => $hookText,
        'desarrollo'         => $desarrollo,
        'cta'                => $ctaText,
        'duracion_estimada'  => $duracion,
        'hashtags'           => array_values($hashtags),
        'meta' => [
            'hook_tipo'  => $hookType,
            'estilo'     => $estiloKey,
            'tema'       => $tema,
            'publico'    => $publico,
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
