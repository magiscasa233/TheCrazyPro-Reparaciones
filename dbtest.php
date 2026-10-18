<?php
/**
 * dbtest.php — Diagnóstico de conexión, independiente del resto del sitio.
 *
 * Cómo usarlo:
 *   1. Súbelo a htdocs/ (la raíz de tu hosting), junto a index.php.
 *   2. Ábrelo en el navegador: https://tudominio.com/dbtest.php
 *   3. Lee el resultado, arregla lo que indique.
 *   4. BÓRRALO del servidor en cuanto termines: revela contraseña
 *      parcial y sirve para atacar el servidor si alguien más lo ve.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>Diagnóstico</title>';
echo '<style>body{font:14px/1.6 ui-monospace,Consolas,monospace;max-width:820px;margin:2rem auto;padding:0 1rem;background:#111;color:#eee}
h1{font:700 18px system-ui}
.ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}
table{border-collapse:collapse;width:100%;margin:1rem 0}
td,th{border:1px solid #333;padding:.5rem .7rem;text-align:left;vertical-align:top}
code{background:#222;padding:.15em .4em;border-radius:4px}</style>';

echo '<h1>1. Ubicación y lectura del .env</h1>';

$raiz = dirname(__FILE__);
$rutaEnv = $raiz . '/.env';

echo '<table>';
echo '<tr><td>Este script está en</td><td><code>' . htmlspecialchars(__FILE__) . '</code></td></tr>';
echo '<tr><td>Busca el .env en</td><td><code>' . htmlspecialchars($rutaEnv) . '</code></td></tr>';

if (!is_file($rutaEnv)) {
    echo '<tr><td>¿Existe?</td><td class="err">NO — el .env no está en esa carpeta. Revisa que subiste el archivo y que se llama exactamente <code>.env</code> (sin .txt).</td></tr>';
    echo '</table>';
    exit;
}
echo '<tr><td>¿Existe?</td><td class="ok">Sí</td></tr>';
echo '<tr><td>¿Se puede leer?</td><td>' . (is_readable($rutaEnv) ? '<span class="ok">Sí</span>' : '<span class="err">NO — problema de permisos</span>') . '</td></tr>';
echo '</table>';

// --- Lector de .env (copia simplificada del de config.php) -----------
$env = [];
foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
    $linea = trim($linea);
    if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) { continue; }
    [$clave, $valor] = explode('=', $linea, 2);
    $env[trim($clave)] = trim($valor);
}

echo '<h1>2. Lo que el script está leyendo (contraseña parcialmente oculta)</h1>';
echo '<table>';
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'] as $clave) {
    $v = $env[$clave] ?? '(vacío / no está en el archivo)';
    echo '<tr><td>' . $clave . '</td><td><code>' . htmlspecialchars($v) . '</code> (longitud: ' . strlen((string) $v) . ')</td></tr>';
}

$pass = $env['DB_PASS'] ?? '';
$passLen = strlen($pass);
$passOculta = $passLen > 4 ? substr($pass, 0, 2) . str_repeat('•', $passLen - 4) . substr($pass, -2) : str_repeat('•', $passLen);
echo '<tr><td>DB_PASS</td><td><code>' . htmlspecialchars($passOculta) . '</code> (longitud: ' . $passLen . ')</td></tr>';

// Detecta espacios o caracteres invisibles que arruinan la contraseña.
$tieneEspacios = $pass !== trim($pass);
$tieneRaros = (bool) preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $pass);
echo '<tr><td>¿Espacios al inicio/final?</td><td>' . ($tieneEspacios ? '<span class="err">SÍ — esa es tu causa más probable</span>' : '<span class="ok">No</span>') . '</td></tr>';
echo '<tr><td>¿Caracteres de control invisibles?</td><td>' . ($tieneRaros ? '<span class="err">SÍ — vuelve a pegar la contraseña desde el Bloc de notas</span>' : '<span class="ok">No</span>') . '</td></tr>';
echo '</table>';

echo '<h1>3. Intento de conexión real</h1>';

$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '3306';
$name = $env['DB_NAME'] ?? '';
$user = $env['DB_USER'] ?? '';

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
echo '<p>DSN: <code>' . htmlspecialchars($dsn) . '</code></p>';
echo '<p>Servidor donde corre este PHP (IP interna, es normal que sea una IP privada): <code>' . htmlspecialchars($_SERVER['SERVER_ADDR'] ?? 'desconocida') . '</code></p>';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]);
    echo '<p class="ok"><strong>✔ Conectó correctamente.</strong></p>';

    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo '<h1>4. Tablas encontradas en <code>' . htmlspecialchars($name) . '</code></h1>';
    if (!$tablas) {
        echo '<p class="warn">La base está vacía. Falta importar <code>schema_hosting.sql</code> aquí.</p>';
    } else {
        echo '<p>' . count($tablas) . ' tablas: ' . htmlspecialchars(implode(', ', $tablas)) . '</p>';
        echo count($tablas) === 12
            ? '<p class="ok">Coincide con las 12 tablas esperadas.</p>'
            : '<p class="warn">Se esperaban 12. Revisa si faltó alguna o sobran de una importación anterior.</p>';
    }
} catch (PDOException $e) {
    echo '<p class="err"><strong>✘ No conectó.</strong></p>';
    echo '<p>Mensaje exacto: <code>' . htmlspecialchars($e->getMessage()) . '</code></p>';

    if (strpos($e->getMessage(), '1045') !== false) {
        echo '<p class="warn">Error 1045 = usuario o contraseña incorrectos para MySQL. '
           . 'No es un problema de PHP ni de tu código: revisa DB_USER y DB_PASS arriba. '
           . 'Regenera la contraseña en el panel de InfinityFree y vuelve a pegarla aquí, '
           . 'copiándola primero a un Bloc de notas para limpiar espacios.</p>';
    } elseif (strpos($e->getMessage(), '1044') !== false) {
        echo '<p class="warn">Error 1044 = la contraseña es correcta, pero ese usuario no tiene permiso '
           . 'sobre la base <code>' . htmlspecialchars($name) . '</code>. Revisa que DB_NAME coincida '
           . 'EXACTAMENTE con el nombre que aparece en "List of MySQL Databases" del panel.</p>';
    } elseif (strpos($e->getMessage(), '2002') !== false || strpos($e->getMessage(), 'timed out') !== false) {
        echo '<p class="warn">No se pudo alcanzar el servidor. Revisa DB_HOST (debe ser exactamente '
           . '<code>sql109.infinityfree.com</code>, sin https:// ni barras) y que no haya typos.</p>';
    }
}

echo '<hr><p class="warn">Recuerda borrar este archivo (<code>dbtest.php</code>) del servidor cuando termines.</p>';
