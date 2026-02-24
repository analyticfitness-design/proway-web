<?php
declare(strict_types=1);

/**
 * POST /api/ai/suggest-content
 * ProWay Lab — Template-based content suggestion engine.
 * Body: { "nicho": "fitness", "plataforma": "instagram" }
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';

requireMethod('POST');

// Rate limit: 10 per IP per hour
if (!checkRateLimit('suggest_content', 10, 3600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Limite alcanzado. Intenta de nuevo en una hora.']);
    exit;
}

$body       = getJsonBody();
$nicho      = trim($body['nicho'] ?? 'fitness');
$plataforma = trim($body['plataforma'] ?? 'instagram');

// ─── Content Ideas Pool (30+ ideas, organized by category) ────────────────────

$ideasPool = [

    // ── NUTRICION (7) ─────────────────────────────────────────────────────────
    [
        'categoria'       => 'nutricion',
        'titulo'          => 'El mito de las 6 comidas al dia',
        'formato'         => 'reel',
        'hook_sugerido'   => '¿Todavia crees que necesitas comer 6 veces al dia para acelerar el metabolismo? La ciencia dice otra cosa.',
        'por_que_funciona'=> 'Derriba un mito popular con datos. Genera debate y guardados.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'nutricion',
        'titulo'          => '3 desayunos de coach en menos de 5 minutos',
        'formato'         => 'reel',
        'hook_sugerido'   => 'No tengo tiempo para desayunar — mentira. 3 opciones en menos de 5 minutos.',
        'por_que_funciona'=> 'Contenido practico y aspiracional. Los "day in the life" de comida funcionan siempre.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'nutricion',
        'titulo'          => '¿Proteina antes o despues de entrenar? La verdad',
        'formato'         => 'reel',
        'hook_sugerido'   => 'La ventana anabolica... ¿existe o no? Esto dice la evidencia mas reciente.',
        'por_que_funciona'=> 'Tema clasico con opinion dividida. Genera comentarios y compartidos.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'nutricion',
        'titulo'          => 'Suplementos que SI valen la pena (y cuales no)',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'Solo 3 suplementos tienen evidencia solida. El resto es marketing.',
        'por_que_funciona'=> 'Lista concreta y controversial. Alto ratio de guardados.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'nutricion',
        'titulo'          => 'Como calcular macros para tus clientes en 2 minutos',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Deja de complicarte con los macros. Este metodo rapido es el que uso con todos mis clientes.',
        'por_que_funciona'=> 'Tutorial directo que ahorra tiempo. Los coaches lo guardan como referencia.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'nutricion',
        'titulo'          => 'Errores de nutricion que sabotean la hipertrofia',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'Comes "bien" pero no creces. Estos 4 errores explican por que.',
        'por_que_funciona'=> 'Apela al pain point directo. Genera identificacion y compartidos.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'nutricion',
        'titulo'          => 'Guia rapida: hidratacion para rendimiento',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Si tus clientes no rinden, puede que el problema sea tan simple como el agua.',
        'por_que_funciona'=> 'Tema subestimado, genera curiosidad. Facil de producir.',
        'dificultad'      => 'facil',
    ],

    // ── ENTRENAMIENTO (7) ─────────────────────────────────────────────────────
    [
        'categoria'       => 'entrenamiento',
        'titulo'          => 'La tecnica que duplica la activacion muscular',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Cambiar ESTO en tu ejecucion puede duplicar la activacion muscular. Te lo demuestro.',
        'por_que_funciona'=> 'Promesa de resultado inmediato + demostracion visual. Alto engagement.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'entrenamiento',
        'titulo'          => '5 ejercicios que la mayoria hace mal',
        'formato'         => 'reel',
        'hook_sugerido'   => 'El 80% de las personas hace mal estos 5 ejercicios. ¿Tu tambien?',
        'por_que_funciona'=> 'Formato "error + correccion" genera mucho guardado y compartido.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'entrenamiento',
        'titulo'          => 'Periodizacion simple para clientes intermedios',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'Tu cliente lleva meses estancado. Este esquema de periodizacion lo desbloquea.',
        'por_que_funciona'=> 'Contenido tecnico que posiciona como experto. Alto valor percibido.',
        'dificultad'      => 'dificil',
    ],
    [
        'categoria'       => 'entrenamiento',
        'titulo'          => 'Sentadilla profunda: ¿si o no?',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Te dijeron que la sentadilla profunda es peligrosa. Vamos a ver que dice la ciencia real.',
        'por_que_funciona'=> 'Tema polarizante con respuesta basada en evidencia. Genera debate masivo.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'entrenamiento',
        'titulo'          => 'Rutina minimalista: resultados con 3 dias/semana',
        'formato'         => 'carrusel',
        'hook_sugerido'   => '¿3 dias a la semana son suficientes? Si sigues esta estructura, sobran.',
        'por_que_funciona'=> 'Apela a quienes tienen poco tiempo. Muy compartible.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'entrenamiento',
        'titulo'          => 'Calentamiento que previene el 80% de lesiones',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Este calentamiento de 5 minutos puede evitarte meses de lesion. Pruebalo hoy.',
        'por_que_funciona'=> 'Contenido de prevencion con urgencia implicita. Se guarda mucho.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'entrenamiento',
        'titulo'          => 'Deload: cuando y como hacerlo correctamente',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Si nunca haces deload, estas acumulando fatiga que te va a pasar factura.',
        'por_que_funciona'=> 'Tema tecnico que pocos explican bien. Posiciona como coach serio.',
        'dificultad'      => 'media',
    ],

    // ── MINDSET (6) ───────────────────────────────────────────────────────────
    [
        'categoria'       => 'mindset',
        'titulo'          => 'Por que tus clientes abandonan (y no es falta de voluntad)',
        'formato'         => 'reel',
        'hook_sugerido'   => 'No es disciplina. No es motivacion. Es otra cosa. Y tiene solucion.',
        'por_que_funciona'=> 'Reframe poderoso de un problema comun. Genera identificacion masiva.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'mindset',
        'titulo'          => 'El habito mas importante para un coach fitness',
        'formato'         => 'reel',
        'hook_sugerido'   => 'No es entrenar. No es comer bien. Es algo que el 95% de coaches no hace.',
        'por_que_funciona'=> 'Intriga + revelacion. Formato que retiene hasta el final.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'mindset',
        'titulo'          => 'Como manejar clientes que no cumplen el plan',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Tu cliente no sigue el plan. Antes de frustrarte, prueba esto.',
        'por_que_funciona'=> 'Pain point directo de coaches. Genera mucha interaccion en comentarios.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'mindset',
        'titulo'          => 'La mentalidad que separa coaches buenos de coaches elite',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'Ser buen coach no es suficiente. Estos 5 cambios de mentalidad marcan la diferencia.',
        'por_que_funciona'=> 'Contenido aspiracional que genera guardados y compartidos entre coaches.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'mindset',
        'titulo'          => 'Sindrome del impostor en coaches nuevos',
        'formato'         => 'reel',
        'hook_sugerido'   => '¿Sientes que no sabes lo suficiente para cobrar? Esto va para ti.',
        'por_que_funciona'=> 'Tema vulnerable y honesto. Genera conexion emocional y comunidad.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'mindset',
        'titulo'          => 'Burnout en coaches: senales y como evitarlo',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'Si sientes que ya no disfrutas ser coach, puede que estes aqui. Hay salida.',
        'por_que_funciona'=> 'Contenido de salud mental que genera empatia y se comparte en privado.',
        'dificultad'      => 'media',
    ],

    // ── NEGOCIO (7) ───────────────────────────────────────────────────────────
    [
        'categoria'       => 'negocio',
        'titulo'          => 'Cuanto cobrar como coach online en LATAM',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Si cobras menos de esto como coach online, estas regalando tu trabajo.',
        'por_que_funciona'=> 'Tema de dinero siempre genera clicks. Controversial y practico a la vez.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'negocio',
        'titulo'          => 'De 0 a 20 clientes online: la hoja de ruta',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'No necesitas miles de seguidores. Necesitas esta estrategia de 5 pasos.',
        'por_que_funciona'=> 'Contenido accionable con promesa concreta. Se guarda como referencia.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'negocio',
        'titulo'          => '3 fuentes de ingreso que todo coach deberia tener',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Si tu unico ingreso es el 1 a 1, estas construyendo un techo. Diversifica asi.',
        'por_que_funciona'=> 'Perspectiva de negocio que muchos coaches no consideran. Alto valor.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'negocio',
        'titulo'          => 'El funnel mas simple para coaches fitness',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'No necesitas funnel complicado. Este de 3 pasos convierte y es gratis.',
        'por_que_funciona'=> 'Simplifica algo que intimida a muchos coaches. Altamente guardable.',
        'dificultad'      => 'dificil',
    ],
    [
        'categoria'       => 'negocio',
        'titulo'          => 'Contenido que vende sin vender',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Deja de pedir "agenda tu llamada" en cada post. Esto funciona mejor.',
        'por_que_funciona'=> 'Contenido sobre contenido — meta pero muy util. Coaches lo guardan.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'negocio',
        'titulo'          => 'Como hacer un testimonio de cliente que convierte',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Tus testimonios no convierten porque les falta ESTO. Te enseno el formato.',
        'por_que_funciona'=> 'Tutorial de prueba social. Practico e inmediatamente aplicable.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'negocio',
        'titulo'          => 'Automatiza tu onboarding de clientes',
        'formato'         => 'carrusel',
        'hook_sugerido'   => '¿Todavia mandas PDFs por WhatsApp? Hay una forma mas profesional (y mas facil).',
        'por_que_funciona'=> 'Resuelve un dolor operativo real. Coaches con experiencia lo valoran mucho.',
        'dificultad'      => 'dificil',
    ],

    // ── TENDENCIAS (6) ────────────────────────────────────────────────────────
    [
        'categoria'       => 'tendencias',
        'titulo'          => 'Tendencias fitness 2026 que deberias aprovechar',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'Estas 5 tendencias van a dominar el fitness este ano. ¿Ya las estas usando?',
        'por_que_funciona'=> 'Contenido de tendencia con urgencia temporal. Genera FOMO positivo.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'tendencias',
        'titulo'          => 'Formatos de reel que estan funcionando ahora',
        'formato'         => 'reel',
        'hook_sugerido'   => 'El algoritmo cambio. Estos 3 formatos son los que mas alcance tienen hoy.',
        'por_que_funciona'=> 'Meta-contenido sobre redes sociales. Coaches quieren saber que funciona.',
        'dificultad'      => 'facil',
    ],
    [
        'categoria'       => 'tendencias',
        'titulo'          => 'IA para coaches fitness: herramientas utiles',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'La IA no te va a reemplazar, pero el coach que la use SI. Estas herramientas te dan ventaja.',
        'por_que_funciona'=> 'Tema caliente. Genera curiosidad y posiciona como innovador.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'tendencias',
        'titulo'          => 'Lo que aprendimos del boom de Ozempic',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Ozempic cambio la conversacion sobre perdida de peso. Y eso nos afecta como coaches.',
        'por_que_funciona'=> 'Tema de actualidad con angulo profesional. Genera debate educado.',
        'dificultad'      => 'dificil',
    ],
    [
        'categoria'       => 'tendencias',
        'titulo'          => '¿El entrenamiento zona 2 vale la pena?',
        'formato'         => 'reel',
        'hook_sugerido'   => 'Todo el mundo habla de zona 2. Pero... ¿realmente es tan bueno como dicen?',
        'por_que_funciona'=> 'Tendencia actual evaluada con ojo critico. Alto ratio de comentarios.',
        'dificultad'      => 'media',
    ],
    [
        'categoria'       => 'tendencias',
        'titulo'          => 'Wearables: que datos realmente importan',
        'formato'         => 'carrusel',
        'hook_sugerido'   => 'Tu reloj te da 50 metricas. Solo 3 importan para tus clientes.',
        'por_que_funciona'=> 'Filtra informacion util de la que no lo es. Muy guardable.',
        'dificultad'      => 'facil',
    ],
];

// ─── Selection Logic ──────────────────────────────────────────────────────────

// Filter by nicho if specific
$nichoLower = mb_strtolower($nicho, 'UTF-8');

$nichoMap = [
    'nutricion'     => 'nutricion',
    'entrenamiento' => 'entrenamiento',
    'training'      => 'entrenamiento',
    'mindset'       => 'mindset',
    'negocio'       => 'negocio',
    'business'      => 'negocio',
    'tendencias'    => 'tendencias',
    'trends'        => 'tendencias',
];

$targetCategory = $nichoMap[$nichoLower] ?? null;

if ($targetCategory) {
    // Get ideas from target category + sprinkle 1-2 from others
    $targetIdeas = array_filter($ideasPool, fn(array $i) => $i['categoria'] === $targetCategory);
    $otherIdeas  = array_filter($ideasPool, fn(array $i) => $i['categoria'] !== $targetCategory);

    $targetIdeas = array_values($targetIdeas);
    $otherIdeas  = array_values($otherIdeas);

    shuffle($targetIdeas);
    shuffle($otherIdeas);

    $selected = array_merge(
        array_slice($targetIdeas, 0, min(4, count($targetIdeas))),
        array_slice($otherIdeas, 0, 1)
    );
} else {
    // Generic: pick 5 from different categories
    shuffle($ideasPool);
    $seen = [];
    $selected = [];
    foreach ($ideasPool as $idea) {
        $cat = $idea['categoria'];
        if (($seen[$cat] ?? 0) < 2) {
            $selected[] = $idea;
            $seen[$cat] = ($seen[$cat] ?? 0) + 1;
        }
        if (count($selected) >= 5) break;
    }
}

// Ensure exactly 5
shuffle($selected);
$selected = array_slice($selected, 0, 5);

// Adapt format label for platform
$formatMap = [
    'tiktok'   => ['reel' => 'video corto', 'carrusel' => 'video corto'],
    'youtube'  => ['reel' => 'short', 'carrusel' => 'short o video largo'],
    'linkedin' => ['reel' => 'post con imagen', 'carrusel' => 'documento PDF'],
];

$platformFormats = $formatMap[mb_strtolower($plataforma, 'UTF-8')] ?? [];

// Build output
$ideas = [];
foreach ($selected as $idea) {
    $formato = $idea['formato'];
    if (isset($platformFormats[$formato])) {
        $formato = $platformFormats[$formato];
    }

    $ideas[] = [
        'titulo'           => $idea['titulo'],
        'formato'          => $formato,
        'hook_sugerido'    => $idea['hook_sugerido'],
        'por_que_funciona' => $idea['por_que_funciona'],
        'dificultad'       => $idea['dificultad'],
        'categoria'        => $idea['categoria'],
    ];
}

// ─── Response ─────────────────────────────────────────────────────────────────

echo json_encode([
    'ok'         => true,
    'ideas'      => $ideas,
    'meta'       => [
        'nicho'      => $nicho,
        'plataforma' => $plataforma,
        'total_pool' => count($ideasPool),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
