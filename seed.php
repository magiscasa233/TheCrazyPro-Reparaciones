<?php
/**
 * CrazyPro-Reparaciones — Siembra inicial de datos.
 *
 * Cómo ejecutarlo (elige una):
 *
 *   A) Consola (recomendado, pide las contraseñas sin mostrarlas):
 *      cd C:\wamp64\www\CrazyPro-Reparaciones
 *      C:\wamp64\bin\php\php8.2.x\php.exe database\seed.php
 *
 *   B) Con variables de entorno en .env, antes de abrirlo en el navegador:
 *      SEED_PASS_ALE=...
 *      SEED_PASS_JMACH=...
 *      Luego: http://localhost/CrazyPro-Reparaciones/database/seed.php
 *
 * Las contraseñas NUNCA se guardan en texto plano: se hashean con el
 * algoritmo por defecto de PHP (bcrypt/Argon2) antes de insertarse, y
 * deben cambiarse en cuanto el sitio pase a producción.
 *
 * El script es idempotente: puedes correrlo varias veces sin duplicar.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

$esConsola = (PHP_SAPI === 'cli');

if (!$esConsola) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Siembra inicial</title>'
       . '<style>body{font:15px/1.6 system-ui;max-width:760px;margin:3rem auto;padding:0 1.5rem;background:#0A0A0B;color:#F5F5F5}'
       . 'code{background:#1C1C21;padding:.15em .4em;border-radius:4px}'
       . '.ok{color:#21C07A}.warn{color:#FFB020}.err{color:#FF1E2D}h1{font-size:1.6rem}</style>';
    echo '<h1>Siembra inicial de CrazyPro-Reparaciones</h1>';
}

function decir(string $texto, string $clase = ''): void
{
    if (PHP_SAPI === 'cli') {
        echo strip_tags($texto), PHP_EOL;
    } else {
        echo '<p class="' . $clase . '">' . $texto . '</p>';
    }
}

/** Pide una contraseña por consola sin mostrarla en pantalla. */
function pedir_password(string $etiqueta): string
{
    if (PHP_SAPI !== 'cli') {
        return '';
    }
    echo $etiqueta . ': ';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows no admite stty; se lee de forma normal.
        $valor = trim((string) fgets(STDIN));
    } else {
        shell_exec('stty -echo');
        $valor = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return $valor;
}

$pdo = db();

// =====================================================================
// 1. Administradores
// =====================================================================
decir('<strong>1. Cuentas de administrador</strong>');

$admins = [
    ['usuario' => 'Ale',   'nombre' => 'Ale',   'env' => 'SEED_PASS_ALE'],
    ['usuario' => 'JMACH', 'nombre' => 'JMACH', 'env' => 'SEED_PASS_JMACH'],
];

foreach ($admins as $a) {
    $st = $pdo->prepare('SELECT id FROM admins WHERE usuario = ? LIMIT 1');
    $st->execute([$a['usuario']]);
    if ($st->fetchColumn()) {
        decir('· ' . $a['usuario'] . ': ya existe, no se toca.', 'warn');
        continue;
    }

    $pass = (string) env($a['env'], '');
    if ($pass === '' && $esConsola) {
        $pass = pedir_password('Contraseña para ' . $a['usuario'] . ' (mínimo 10 caracteres)');
    }

    if (strlen($pass) < 10) {
        decir('· ' . $a['usuario'] . ': <strong>no se creó</strong>. Define <code>' . $a['env']
            . '</code> en el archivo .env con al menos 10 caracteres, o ejecuta este script desde la consola.', 'err');
        continue;
    }

    $ins = $pdo->prepare('INSERT INTO admins (usuario, password_hash, nombre) VALUES (?, ?, ?)');
    $ins->execute([$a['usuario'], password_hash($pass, PASSWORD_DEFAULT), $a['nombre']]);
    decir('· ' . $a['usuario'] . ': creado con contraseña hasheada.', 'ok');
    $pass = str_repeat('*', strlen($pass));   // borra de memoria
}

// =====================================================================
// 2. Estados de orden
// =====================================================================
decir('<strong>2. Estados de orden</strong>');

$estados = [
    // nombre,           pos, color,     inicial, final, protegido
    ['Recibido',          10, '#8B5CF6', 1, 0, 1],
    ['En diagnóstico',    20, '#3B82F6', 0, 0, 0],
    ['Cotización enviada',30, '#F59E0B', 0, 0, 0],
    ['Esperando piezas',  40, '#EAB308', 0, 0, 0],
    ['En reparación',     50, '#FF1E2D', 0, 0, 0],
    ['Listo para recoger',60, '#21C07A', 0, 0, 0],
    ['Entregado',         70, '#6B7280', 0, 1, 1],
];

$st = $pdo->prepare(
    'INSERT INTO estados_orden (nombre, posicion, color, es_inicial, es_final, protegido)
     SELECT ?, ?, ?, ?, ?, ?
     FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM estados_orden WHERE nombre = ?)'
);
$nuevos = 0;
foreach ($estados as $e) {
    $st->execute([$e[0], $e[1], $e[2], $e[3], $e[4], $e[5], $e[0]]);
    $nuevos += $st->rowCount();
}
decir('· ' . $nuevos . ' estados nuevos (los existentes se conservan).', 'ok');

// =====================================================================
// 3. Catálogo de servicios (sección 6 del brief)
// =====================================================================
decir('<strong>3. Catálogo de servicios</strong>');

$servicios = [
    // Cómputo
    ['computo', 'Instalación de Windows optimizado', 'Windows listo para trabajar: Office, Chrome y los programas esenciales, desde imagen preconfigurada con activación OEM.', null],
    ['computo', 'Migración de HDD a SSD', 'Pasamos tu sistema tal cual al SSD. Arranca en segundos y no pierdes nada.', null],
    ['computo', 'Aumento de memoria RAM', 'Revisamos qué admite tu equipo, conseguimos el módulo correcto y lo instalamos.', null],
    ['computo', 'Limpieza y cambio de pasta térmica', 'Limpieza física profunda, pasta térmica nueva y optimización general. Baja temperaturas y ruido.', null],
    ['computo', 'Instalación de Linux', 'Ubuntu, Fedora, Debian, Arch o la distribución que necesites, configurada y funcionando.', null],
    ['computo', 'Hackintosh', 'macOS en hardware de PC compatible, con el proceso de arranque configurado a tu equipo.', null],
    ['computo', 'Reparación de laptop', 'Pantalla, teclado, trackpad y puertos. Diagnóstico primero, costo confirmado después.', null],
    ['computo', 'Modding estético', 'Pintura, pulido y personalización para que tu equipo se vea como tú quieres.', null],
    ['computo', 'Ensamble y proyectos especiales', 'Armamos tu equipo a la medida, incluidas conversiones como PC vieja a consola retro con Batocera.', null],

    // Android
    ['android', 'Cambio de pantalla Android', 'Pantalla nueva instalada y probada antes de entregártelo.', null],
    ['android', 'Cambio de batería Android', 'Batería nueva, sin los apagones repentinos al 30 %.', null],
    ['android', 'Cambio de bocina, micrófono o cámara', 'Si te escuchan mal o las fotos salen borrosas, casi siempre es el módulo.', null],
    ['android', 'Cambio de carcasa', 'Devuélvele el aspecto de nuevo: tapa, marco o ambos.', null],

    // iPhone
    ['iphone', 'Cambio de batería iPhone', 'Batería nueva con salud al 100 % desde el primer día.', null],
    ['iphone', 'Cambio de pantalla iPhone', 'Genérica, LCD, OLED u original: te explicamos la diferencia y tú eliges.', null],
    ['iphone', 'Cambio de cristal iPhone', 'Solo el cristal, genérico u original, sin tocar el panel si está sano.', null],
    ['iphone', 'Cambio de bocina iPhone', 'Auricular o bocina de altavoz, según dónde esté el problema.', null],

    // Desarrollo
    ['desarrollo', 'Desarrollo de páginas web', 'Landing, catálogo o sistema a medida. Desde el diseño hasta la publicación.', null],
    ['desarrollo', 'Integración de inteligencia artificial', 'IA aplicada a tu negocio: automatizaciones, asistentes y procesos que ahorran horas.', null],
];

$st = $pdo->prepare(
    'INSERT INTO servicios (categoria, nombre, descripcion, precio, posicion, activo)
     SELECT ?, ?, ?, ?, ?, 1
     FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM servicios WHERE nombre = ?)'
);
$nuevos = 0;
$pos = 0;
foreach ($servicios as $s) {
    $pos += 10;
    $st->execute([$s[0], $s[1], $s[2], $s[3], $pos, $s[1]]);
    $nuevos += $st->rowCount();
}
decir('· ' . $nuevos . ' servicios nuevos. Todos con precio NULL = "Precio por definir".', 'ok');

// =====================================================================
// 4. Contenido editable (CMS)
// =====================================================================
decir('<strong>4. Contenido del sitio</strong>');

$contenido = [
    // clave, valor, tipo, grupo, etiqueta, editable
    ['sitio_nombre', 'CrazyPro-Reparaciones', 'texto', 'general', 'Nombre del sitio', 1],
    ['sitio_tagline', 'Reparamos, optimizamos y devolvemos a la vida tu equipo.', 'texto', 'general', 'Lema del pie de página', 1],
    ['sitio_descripcion', 'Taller de reparación y optimización de computadoras, celulares Android e iPhone en Huehuetoca, Estado de México.', 'textarea', 'general', 'Descripción para buscadores', 1],
    ['logo_url', '', 'imagen', 'general', 'Logo del sitio', 1],

    ['hero_etiqueta', 'Huehuetoca, Estado de México', 'texto', 'portada', 'Etiqueta sobre el título', 1],
    ['hero_titulo', "Tu equipo no está muerto.\nTodavía no lo hemos tocado.", 'textarea', 'portada', 'Título principal', 1],
    ['hero_subtitulo', 'Reparación y optimización de PC, laptop, Android e iPhone. Diagnóstico honesto, piezas que sí duran y un folio para seguir tu equipo paso a paso.', 'textarea', 'portada', 'Texto bajo el título', 1],
    ['dato_1_valor', '24 h', 'texto', 'portada', 'Dato 1 · valor', 1],
    ['dato_1_texto', 'Diagnóstico promedio', 'texto', 'portada', 'Dato 1 · etiqueta', 1],
    ['dato_2_valor', '90 días', 'texto', 'portada', 'Dato 2 · valor', 1],
    ['dato_2_texto', 'Garantía en piezas', 'texto', 'portada', 'Dato 2 · etiqueta', 1],
    ['dato_3_valor', '4', 'texto', 'portada', 'Dato 3 · valor', 1],
    ['dato_3_texto', 'Especialidades', 'texto', 'portada', 'Dato 3 · etiqueta', 1],

    ['servicios_titulo', 'Qué reparamos', 'texto', 'portada', 'Título del catálogo', 1],
    ['servicios_intro', 'Precios claros. Si un servicio dice “Precio por definir” es porque depende del diagnóstico: te lo confirmamos antes de tocar nada.', 'textarea', 'portada', 'Introducción del catálogo', 1],
    ['cta_titulo', 'Dinos qué le pasa a tu equipo', 'texto', 'portada', 'Título del llamado final', 1],
    ['cta_texto', 'Respondemos el mismo día. Sin compromiso y sin letras chiquitas.', 'texto', 'portada', 'Texto del llamado final', 1],

    ['whatsapp_numero', '5217299244238', 'texto', 'contacto', 'Número de WhatsApp (con lada país)', 1],
    ['telefono_publico', '729-924-4238', 'texto', 'contacto', 'Teléfono visible', 1],
    ['correo_publico', 'contacto@crazypro-reparaciones.com', 'texto', 'contacto', 'Correo visible', 1],
    ['direccion', 'Huehuetoca, Estado de México', 'texto', 'contacto', 'Dirección', 1],
    ['horario', 'Lunes a sábado, 10:00 – 19:00', 'texto', 'contacto', 'Horario de atención', 1],
    ['wa_msg_generico', '¡Hola! Quiero más información sobre sus servicios.', 'textarea', 'contacto', 'Mensaje rápido de WhatsApp', 1],
    ['politica_desbloqueo', 'No realizamos desbloqueo de equipos robados ni retiro de cuentas de procedencia dudosa.', 'textarea', 'contacto', 'Política visible en el pie', 1],

    ['admin_slug', 'acceso-x7k9qz.php', 'texto', 'sistema', 'Archivo de acceso al panel', 0],
];

$st = $pdo->prepare(
    'INSERT INTO contenido (clave, valor, tipo, grupo, etiqueta, posicion, editable)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE etiqueta = VALUES(etiqueta), tipo = VALUES(tipo), grupo = VALUES(grupo)'
);
$pos = 0;
foreach ($contenido as $c) {
    $pos += 10;
    $st->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $pos, $c[5]]);
}
decir('· ' . count($contenido) . ' claves de contenido listas (los textos ya editados no se sobrescriben).', 'ok');

// =====================================================================
// 5. Aliado de ejemplo
// =====================================================================
decir('<strong>5. Aliados</strong>');

$st = $pdo->prepare(
    'INSERT INTO aliados (nombre, codigo, contacto, descuento_pct, comision)
     SELECT ?, ?, ?, ?, ?
     FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM aliados WHERE codigo = ?)'
);
$st->execute(['Tienda González', 'TIENDAGONZALES', 'Sr. González', 10.00, 50.00, 'TIENDAGONZALES']);
decir($st->rowCount() > 0 ? '· Aliado de ejemplo creado: TIENDAGONZALES.' : '· El aliado de ejemplo ya existía.', 'ok');

// =====================================================================
decir('<strong>Listo.</strong>');
decir('Entra al panel en: <code>' . e(url('admin/' . admin_slug())) . '</code>');
decir('<span class="warn">Por seguridad, borra o mueve <code>database/seed.php</code> antes de publicar el sitio, y cambia las contraseñas de administrador en producción.</span>', 'warn');
