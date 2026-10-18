<?php
/**
 * CrazyPro-Reparaciones — Punto de arranque único.
 *
 * Carga .env, abre la conexión PDO a MySQL e inicia la sesión.
 * Todo archivo del proyecto empieza con un require de este archivo.
 *
 * No requiere Composer: el lector de .env es propio (unas 30 líneas).
 * Si más adelante instalas vendor/, se carga automáticamente.
 */

declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');
mb_internal_encoding('UTF-8');

define('CP_ROOT', dirname(__DIR__));
define('CP_UPLOADS', CP_ROOT . DIRECTORY_SEPARATOR . 'uploads');

// Autoload de Composer, solo si existe (opcional).
if (is_file(CP_ROOT . '/vendor/autoload.php')) {
    require_once CP_ROOT . '/vendor/autoload.php';
}

// ---------------------------------------------------------------------
// Lector mínimo de .env
// ---------------------------------------------------------------------
function cp_cargar_env(string $ruta): void
{
    if (!is_readable($ruta)) {
        return;
    }
    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);
        // Quita comillas envolventes si las hay.
        $largo = strlen($valor);
        if ($largo >= 2) {
            $ini = $valor[0];
            $fin = $valor[$largo - 1];
            if (($ini === '"' && $fin === '"') || ($ini === "'" && $fin === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }
        $_ENV[$clave] = $valor;
    }
}

/** Lee una variable de entorno con valor por defecto. */
function env(string $clave, $defecto = null)
{
    if (array_key_exists($clave, $_ENV)) {
        $v = $_ENV[$clave];
        if ($v === 'true')  { return true; }
        if ($v === 'false') { return false; }
        if ($v === 'null' || $v === '') { return $defecto; }
        return $v;
    }
    $v = getenv($clave);
    return ($v === false || $v === '') ? $defecto : $v;
}

cp_cargar_env(CP_ROOT . '/.env');

// ---------------------------------------------------------------------
// URL base del sitio (se detecta sola si no está en .env)
// ---------------------------------------------------------------------
if (!function_exists('cp_detectar_base_url')) {
    function cp_detectar_base_url(): string
    {
        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // /CrazyPro-Reparaciones/config → /CrazyPro-Reparaciones
        $script  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $marcas  = ['/auth', '/cliente', '/admin', '/config', '/database', '/includes'];
        foreach ($marcas as $m) {
            if (substr($script, -strlen($m)) === $m) {
                $script = substr($script, 0, -strlen($m));
                break;
            }
        }
        $script = rtrim($script, '/');
        return $esquema . '://' . $host . $script;
    }
}

define('CP_URL', rtrim((string) env('APP_URL', cp_detectar_base_url()), '/'));
define('CP_DEBUG', (bool) env('APP_DEBUG', true));

if (CP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ---------------------------------------------------------------------
// Conexión a MySQL (PDO, prepared statements siempre reales)
// ---------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host   = (string) env('DB_HOST', '127.0.0.1');
    $puerto = (string) env('DB_PORT', '3306');
    $nombre = (string) env('DB_NAME', 'crazypro');
    $user   = (string) env('DB_USER', 'root');
    $pass   = (string) env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$puerto};dbname={$nombre};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '-06:00'",
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        if (CP_DEBUG) {
            die('<h1 style="font:600 18px system-ui">No se pudo conectar a MySQL</h1>'
                . '<p style="font:14px system-ui">' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
                . '<p style="font:14px system-ui">Revisa <code>.env</code> y que el servicio MySQL de WAMP esté encendido.</p>');
        }
        die('Servicio no disponible temporalmente.');
    }

    return $pdo;
}

// ---------------------------------------------------------------------
// Sesión
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $seguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $seguro,
        'samesite' => 'Lax',
    ]);
    session_name('crazypro_sid');
    session_start();
}
