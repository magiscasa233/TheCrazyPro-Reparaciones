<?php
/**
 * CrazyPro-Reparaciones — Puerta de entrada al panel de administrador.
 *
 * Este archivo se mantiene aparte del router del panel a propósito: es el
 * único punto de entrada y su nombre es el "slug secreto". Para rotarlo:
 *   1. Renombra este archivo (ej. acceso-m4t8rb.php).
 *   2. Cámbialo también en el CMS → Contenido → clave `admin_slug`.
 * (El panel incluye un botón que hace las dos cosas a la vez.)
 *
 * No debe existir ningún enlace público hacia aquí.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

// Ni buscadores ni archivos guardan esta URL.
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Referrer-Policy: no-referrer');

// Si el slug guardado en el CMS ya no coincide con el nombre de este
// archivo, la puerta está "vieja": se comporta como página inexistente.
$slugEsperado = admin_slug();
if ($slugEsperado !== '' && basename(__FILE__) !== $slugEsperado) {
    http_response_code(404);
    exit('<!doctype html><title>404</title><h1>404 Not Found</h1>');
}

if (es_admin()) {
    redirigir('admin/admin.php?view=dashboard');
}

const MAX_INTENTOS  = 5;      // por IP
const VENTANA_MIN   = 15;     // minutos

$error    = '';
$usuario  = '';
$bloqueado = false;

// --- Rate limiting ----------------------------------------------------
// VENTANA_MIN es una constante del propio archivo, no entrada del usuario:
// se interpola para que MySQL acepte el INTERVAL. La IP sí va como parámetro.
$st = db()->prepare(
    'SELECT COUNT(*) FROM login_intentos
     WHERE ip = ? AND exito = 0 AND creado_en > (NOW() - INTERVAL ' . (int) VENTANA_MIN . ' MINUTE)'
);
$st->execute([ip_cliente()]);
$fallidos = (int) $st->fetchColumn();

if ($fallidos >= MAX_INTENTOS) {
    $bloqueado = true;
    $error = 'Demasiados intentos fallidos. Espera ' . VENTANA_MIN . ' minutos antes de volver a intentar.';
}

// --- Intento de acceso ------------------------------------------------
if (!$bloqueado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();

    $usuario = limpiar($_POST['usuario'] ?? '', 60);
    $pass    = (string) ($_POST['password'] ?? '');

    $q = db()->prepare('SELECT id, password_hash FROM admins WHERE usuario = ? AND activo = 1 LIMIT 1');
    $q->execute([$usuario]);
    $admin = $q->fetch();

    $ok = $admin && password_verify($pass, $admin['password_hash']);

    $log = db()->prepare('INSERT INTO login_intentos (ip, usuario, exito) VALUES (?, ?, ?)');
    $log->execute([ip_cliente(), $usuario, $ok ? 1 : 0]);

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];

        $up = db()->prepare('UPDATE admins SET ultimo_acceso = NOW() WHERE id = ?');
        $up->execute([$admin['id']]);

        // Limpia el historial de intentos de esta IP.
        $cl = db()->prepare('DELETE FROM login_intentos WHERE ip = ? AND exito = 0');
        $cl->execute([ip_cliente()]);

        redirigir('admin/admin.php?view=dashboard');
    }

    // Mismo mensaje siempre: no revelamos si el usuario existe.
    $error = 'Usuario o contraseña incorrectos.';
    usleep(400000);
}
?>
<!DOCTYPE html>
<html lang="es" data-tema="oscuro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Acceso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>?v=2">
</head>
<body>
<div class="login-centro">
  <svg class="pcb" viewBox="0 0 900 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <path d="M-20 180 H200 L260 240 H460"/>
    <path d="M920 420 H700 L640 360 H440"/>
    <circle cx="260" cy="240" r="4"/>
    <circle cx="640" cy="360" r="4"/>
  </svg>

  <div class="login-caja">
    <div class="panel-form">
      <h1 style="font-size:1.6rem">Panel interno</h1>
      <p class="apagado pequeno" style="margin-bottom:1.5rem">Acceso solo para administradores de CrazyPro-Reparaciones.</p>

      <?php if ($error !== ''): ?>
        <div class="aviso aviso--error" style="width:auto;margin:0 0 1.2rem" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_campo() ?>

        <div class="campo">
          <label for="usuario">Usuario</label>
          <input type="text" id="usuario" name="usuario" value="<?= e($usuario) ?>" required autofocus <?= $bloqueado ? 'disabled' : '' ?>>
        </div>

        <div class="campo">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" required <?= $bloqueado ? 'disabled' : '' ?>>
        </div>

        <button class="btn btn--rojo btn--bloque" type="submit" <?= $bloqueado ? 'disabled' : '' ?>>Entrar</button>
      </form>

      <?php if (!$bloqueado && $fallidos > 0): ?>
        <p class="ayuda centro" style="margin-top:1rem">
          Intentos restantes: <?= MAX_INTENTOS - $fallidos ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
