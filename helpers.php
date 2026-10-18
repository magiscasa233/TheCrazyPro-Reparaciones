<?php
/**
 * CrazyPro-Reparaciones — Utilidades transversales.
 * Sesión, CSRF, folios, formato de precios, subida de archivos y contenido CMS.
 */

declare(strict_types=1);

// =====================================================================
// Salida segura y utilidades de texto
// =====================================================================

/** Escapa para HTML. Úsalo en TODA salida que venga de la base de datos. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Limpia un texto de entrada (no sustituye a los prepared statements). */
function limpiar($valor, int $max = 5000): string
{
    $valor = is_string($valor) ? $valor : '';
    $valor = str_replace(["\0", "\r"], '', $valor);
    return mb_substr(trim($valor), 0, $max);
}

/** Convierte a slug: "Tienda González" → "tienda-gonzalez". */
function slugify(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower((string) $texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim((string) $texto, '-');
}

/** Solo dígitos: útil para teléfonos. */
function solo_digitos($valor): string
{
    return preg_replace('/\D+/', '', (string) $valor) ?? '';
}

// =====================================================================
// Precios: NULL = "por definir"  ·  0.00 = gratuito
// =====================================================================

function precio_fmt($valor, string $siNulo = 'Precio por definir'): string
{
    if ($valor === null || $valor === '') {
        return $siNulo;
    }
    $n = (float) $valor;
    if ($n == 0.0) {
        return 'Sin costo';
    }
    return '$' . number_format($n, 2) . ' MXN';
}

/** Normaliza un precio que viene de un formulario: '' → NULL, '0' → 0.00 */
function precio_desde_form($valor): ?float
{
    if ($valor === null) {
        return null;
    }
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }
    return round((float) str_replace([',', '$', ' '], '', $valor), 2);
}

function fecha_fmt(?string $fecha, bool $conHora = true): string
{
    if (!$fecha) {
        return '—';
    }
    $ts = strtotime($fecha);
    if ($ts === false) {
        return '—';
    }
    $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $txt = date('j', $ts) . ' ' . $meses[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $conHora ? $txt . ', ' . date('H:i', $ts) : $txt;
}

// =====================================================================
// Navegación
// =====================================================================

function url(string $ruta = ''): string
{
    return CP_URL . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta): void
{
    header('Location: ' . (preg_match('#^https?://#', $ruta) ? $ruta : url($ruta)));
    exit;
}

/** Mensaje de un solo uso mostrado en la siguiente pantalla. */
function flash(?string $tipo = null, ?string $mensaje = null)
{
    if ($tipo !== null && $mensaje !== null) {
        $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
        return null;
    }
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

// =====================================================================
// CSRF
// =====================================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Corta la ejecución si el token no coincide. */
function csrf_verificar(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($enviado) || !hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(419);
        die('Tu sesión expiró. Vuelve atrás, recarga la página e inténtalo de nuevo.');
    }
}

// =====================================================================
// Sesión de cliente y de administrador
// =====================================================================

function esta_logueado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function usuario_actual(): ?array
{
    static $cache = null;
    if (!esta_logueado()) {
        return null;
    }
    if ($cache !== null && (int) $cache['id'] === (int) $_SESSION['usuario_id']) {
        return $cache;
    }
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $st->execute([$_SESSION['usuario_id']]);
    $u = $st->fetch();
    if (!$u) {
        unset($_SESSION['usuario_id']);
        return null;
    }
    $cache = $u;
    return $u;
}

function requerir_login(): void
{
    if (!esta_logueado()) {
        $_SESSION['redirigir_a'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Inicia sesión para ver tus reparaciones.');
        redirigir('auth/auth.php?action=login');
    }
}

function es_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function admin_actual(): ?array
{
    if (!es_admin()) {
        return null;
    }
    $st = db()->prepare('SELECT id, usuario, nombre FROM admins WHERE id = ? AND activo = 1 LIMIT 1');
    $st->execute([$_SESSION['admin_id']]);
    $a = $st->fetch();
    return $a ?: null;
}

function requerir_admin(): void
{
    if (!admin_actual()) {
        unset($_SESSION['admin_id']);
        redirigir('admin/' . admin_slug());
    }
}

/** Nombre del archivo de acceso al panel (regenerable desde el CMS). */
function admin_slug(): string
{
    return (string) contenido('admin_slug', 'acceso-x7k9qz.php');
}

// =====================================================================
// Contenido del CMS
// =====================================================================

/** Lee una clave del CMS. Cachea todo el contenido en la primera llamada. */
function contenido(string $clave, $defecto = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT clave, valor FROM contenido') as $fila) {
                $cache[$fila['clave']] = $fila['valor'];
            }
        } catch (PDOException $e) {
            return $defecto;
        }
    }
    $v = $cache[$clave] ?? null;
    return ($v === null || $v === '') ? $defecto : $v;
}

/** Guarda una clave del CMS (la crea si no existe). */
function contenido_guardar(string $clave, $valor, string $etiqueta = '', string $grupo = 'general', string $tipo = 'texto'): void
{
    $st = db()->prepare(
        'INSERT INTO contenido (clave, valor, tipo, grupo, etiqueta)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );
    $st->execute([$clave, $valor, $tipo, $grupo, $etiqueta !== '' ? $etiqueta : $clave]);
}

/** Devuelve la URL pública de una imagen del CMS, con respaldo. */
function img_url(?string $ruta, string $respaldo = 'assets/logo.svg'): string
{
    if (!$ruta) {
        return url($respaldo);
    }
    if (preg_match('#^https?://#', $ruta)) {
        return $ruta;
    }
    return url(ltrim($ruta, '/'));
}

// =====================================================================
// Folio: DDMesAAAA-NN (3 dígitos a partir de la orden 100 del día)
// =====================================================================

function generar_folio(): string
{
    $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $hoy   = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
    $base  = $hoy->format('d') . $meses[(int) $hoy->format('n') - 1] . $hoy->format('Y');

    $st = db()->prepare('SELECT COUNT(*) FROM ordenes WHERE folio LIKE ?');
    $st->execute([$base . '-%']);
    $siguiente = (int) $st->fetchColumn() + 1;

    // Hasta 99 se usan 2 dígitos; de la 100 en adelante, 3. Los folios ya
    // emitidos ese día no cambian: solo crece el ancho de los nuevos.
    for ($intento = 0; $intento < 50; $intento++) {
        $consecutivo = $siguiente <= 99
            ? str_pad((string) $siguiente, 2, '0', STR_PAD_LEFT)
            : (string) $siguiente;
        $folio = $base . '-' . $consecutivo;

        $chk = db()->prepare('SELECT 1 FROM ordenes WHERE folio = ? LIMIT 1');
        $chk->execute([$folio]);
        if (!$chk->fetchColumn()) {
            return $folio;
        }
        $siguiente++;
    }

    return $base . '-' . substr((string) time(), -4);
}

// =====================================================================
// WhatsApp: enlaces wa.me
// =====================================================================

function wa_numero(): string
{
    return solo_digitos(contenido('whatsapp_numero', '5217299244238'));
}

function wa_link(string $mensaje = '', ?string $numero = null): string
{
    $numero = solo_digitos($numero ?? wa_numero());
    return 'https://wa.me/' . $numero . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

// =====================================================================
// Subida de imágenes (CMS y servicios)
// =====================================================================

/**
 * Guarda un archivo de $_FILES en /uploads y devuelve la ruta relativa.
 * Devuelve null si no se subió nada; lanza RuntimeException si algo falla.
 */
function subir_imagen(string $campo, string $prefijo = 'img'): ?string
{
    if (empty($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('La imagen no se pudo subir (código ' . $f['error'] . ').');
    }
    if ($f['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('La imagen pesa más de 4 MB. Comprímela e inténtalo de nuevo.');
    }

    $permitidos = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $info = @getimagesize($f['tmp_name']);
    if ($info === false || !isset($permitidos[$info[2]])) {
        throw new RuntimeException('Formato no admitido. Usa JPG, PNG, GIF o WebP.');
    }

    if (!is_dir(CP_UPLOADS)) {
        @mkdir(CP_UPLOADS, 0775, true);
    }
    $nombre = $prefijo . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $permitidos[$info[2]];
    $destino = CP_UPLOADS . DIRECTORY_SEPARATOR . $nombre;

    if (!move_uploaded_file($f['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo guardar la imagen en /uploads. Revisa permisos de escritura.');
    }

    return 'uploads/' . $nombre;
}

// =====================================================================
// Consultas de uso frecuente
// =====================================================================

function estados_lista(bool $soloActivos = true): array
{
    $sql = 'SELECT * FROM estados_orden' . ($soloActivos ? ' WHERE activo = 1' : '') . ' ORDER BY posicion, id';
    return db()->query($sql)->fetchAll();
}

function estado_inicial_id(): int
{
    $id = db()->query('SELECT id FROM estados_orden WHERE es_inicial = 1 ORDER BY posicion LIMIT 1')->fetchColumn();
    if (!$id) {
        $id = db()->query('SELECT id FROM estados_orden ORDER BY posicion, id LIMIT 1')->fetchColumn();
    }
    return (int) $id;
}

function categorias_servicios(): array
{
    return [
        'computo'    => 'Cómputo · PC y laptop',
        'android'    => 'Celulares Android',
        'iphone'     => 'iPhone',
        'desarrollo' => 'Desarrollo y tecnología',
    ];
}

/** Busca una orden por folio, con su estado. */
function orden_por_folio(string $folio): ?array
{
    $st = db()->prepare(
        'SELECT o.*, e.nombre AS estado_nombre, e.color AS estado_color, e.es_final,
                s.nombre AS servicio_nombre
         FROM ordenes o
         JOIN estados_orden e ON e.id = o.estado_id
         LEFT JOIN servicios s ON s.id = o.servicio_id
         WHERE o.folio = ? LIMIT 1'
    );
    $st->execute([strtoupper(trim($folio))]);
    $o = $st->fetch();
    return $o ?: null;
}

function historial_de_orden(int $ordenId): array
{
    $st = db()->prepare(
        'SELECT h.*, e.nombre AS estado_nombre, e.color AS estado_color
         FROM historial_estados h
         JOIN estados_orden e ON e.id = h.estado_id
         WHERE h.orden_id = ? ORDER BY h.creado_en ASC, h.id ASC'
    );
    $st->execute([$ordenId]);
    return $st->fetchAll();
}

function piezas_de_orden(int $ordenId): array
{
    $st = db()->prepare('SELECT * FROM piezas_orden WHERE orden_id = ? ORDER BY id');
    $st->execute([$ordenId]);
    return $st->fetchAll();
}

/** Total de una orden: presupuesto + piezas con precio, menos descuento. */
function total_orden(array $orden, array $piezas = []): ?float
{
    $tiene = false;
    $suma  = 0.0;
    if ($orden['presupuesto'] !== null) {
        $suma += (float) $orden['presupuesto'];
        $tiene = true;
    }
    foreach ($piezas as $p) {
        if ($p['precio'] !== null) {
            $suma += (float) $p['precio'] * (int) $p['cantidad'];
            $tiene = true;
        }
    }
    if (!$tiene) {
        return null;
    }
    $desc = (float) ($orden['descuento_pct'] ?? 0);
    if ($desc > 0) {
        $suma -= $suma * ($desc / 100);
    }
    return round($suma, 2);
}

function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}
