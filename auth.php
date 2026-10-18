<?php
/**
 * CrazyPro-Reparaciones — Autenticación de clientes.
 *   ?action=login            → formulario e inicio de sesión
 *   ?action=registro         → alta con correo y contraseña
 *   ?action=logout           → cerrar sesión
 *   ?action=google           → redirige al consentimiento de Google
 *   ?action=google_callback  → intercambia el código por el perfil
 *
 * El flujo de Google se hace con cURL directo contra los endpoints
 * oficiales: no hace falta Composer ni ninguna dependencia de Node.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

const GOOGLE_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_USER_URL  = 'https://www.googleapis.com/oauth2/v3/userinfo';

$action = $_GET['action'] ?? 'login';

switch ($action) {
    case 'login':           accion_login();    break;
    case 'registro':        accion_registro(); break;
    case 'logout':          accion_logout();   break;
    case 'google':          accion_google();   break;
    case 'google_callback': accion_google_callback(); break;
    default:
        http_response_code(404);
        redirigir('index.php');
}

// =====================================================================
function google_redirect_uri(): string
{
    return (string) env('GOOGLE_REDIRECT_URI', url('auth/auth.php?action=google_callback'));
}

function google_configurado(): bool
{
    return (bool) env('GOOGLE_CLIENT_ID') && (bool) env('GOOGLE_CLIENT_SECRET');
}

/** Inicia sesión y manda al cliente a donde iba. */
function iniciar_sesion_usuario(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;

    $destino = $_SESSION['redirigir_a'] ?? '';
    unset($_SESSION['redirigir_a']);

    if ($destino !== '' && strpos($destino, '//') === false) {
        header('Location: ' . $destino);
        exit;
    }
    redirigir('cliente/cliente.php?view=panel');
}

// =====================================================================
// LOGIN
// =====================================================================
function accion_login(): void
{
    if (esta_logueado()) {
        redirigir('cliente/cliente.php?view=panel');
    }

    $email  = '';
    $error  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();
        $email = limpiar($_POST['email'] ?? '', 190);
        $pass  = (string) ($_POST['password'] ?? '');

        $st = db()->prepare('SELECT id, password_hash FROM usuarios WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && $u['password_hash'] && password_verify($pass, $u['password_hash'])) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $up = db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
                $up->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            iniciar_sesion_usuario((int) $u['id']);
        }

        if ($u && !$u['password_hash']) {
            $error = 'Esta cuenta se creó con Google. Entra con el botón de Google.';
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    }

    pantalla_auth('login', $email, $error);
}

// =====================================================================
// REGISTRO
// =====================================================================
function accion_registro(): void
{
    if (esta_logueado()) {
        redirigir('cliente/cliente.php?view=panel');
    }

    $email = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();
        $nombre   = limpiar($_POST['nombre'] ?? '', 120);
        $email    = limpiar($_POST['email'] ?? '', 190);
        $whatsapp = solo_digitos($_POST['whatsapp'] ?? '');
        $pass     = (string) ($_POST['password'] ?? '');

        if ($nombre === '') {
            $error = 'Escribe tu nombre.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ese correo no parece válido.';
        } elseif (strlen($pass) < 8) {
            $error = 'La contraseña necesita al menos 8 caracteres.';
        } else {
            $st = db()->prepare('SELECT id, password_hash FROM usuarios WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            $existente = $st->fetch();

            if ($existente && $existente['password_hash']) {
                $error = 'Ese correo ya tiene cuenta. Inicia sesión.';
            } elseif ($existente) {
                // Cuenta creada con Google: le añadimos contraseña.
                $up = db()->prepare('UPDATE usuarios SET password_hash = ?, nombre = ?, whatsapp = ? WHERE id = ?');
                $up->execute([password_hash($pass, PASSWORD_DEFAULT), $nombre, $whatsapp ?: null, $existente['id']]);
                flash('exito', 'Listo, ya puedes entrar con correo o con Google.');
                iniciar_sesion_usuario((int) $existente['id']);
            } else {
                $ins = db()->prepare(
                    'INSERT INTO usuarios (nombre, email, password_hash, whatsapp) VALUES (?, ?, ?, ?)'
                );
                $ins->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT), $whatsapp ?: null]);
                $nuevoId = (int) db()->lastInsertId();

                // Vincula cotizaciones previas hechas con ese mismo correo.
                $lnk = db()->prepare('UPDATE ordenes SET usuario_id = ? WHERE usuario_id IS NULL AND email_contacto = ?');
                $lnk->execute([$nuevoId, $email]);

                flash('exito', '¡Cuenta creada! Aquí verás el avance de tus equipos.');
                iniciar_sesion_usuario($nuevoId);
            }
        }
    }

    pantalla_auth('registro', $email, $error);
}

// =====================================================================
function accion_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    flash('info', 'Cerraste sesión. ¡Hasta la próxima!');
    redirigir('index.php');
}

// =====================================================================
// GOOGLE OAUTH 2.0
// =====================================================================
function accion_google(): void
{
    if (!google_configurado()) {
        flash('error', 'El acceso con Google todavía no está configurado. Usa tu correo y contraseña.');
        redirigir('auth/auth.php?action=login');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $parametros = [
        'client_id'     => (string) env('GOOGLE_CLIENT_ID'),
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];

    header('Location: ' . GOOGLE_AUTH_URL . '?' . http_build_query($parametros));
    exit;
}

function accion_google_callback(): void
{
    if (!google_configurado()) {
        redirigir('auth/auth.php?action=login');
    }

    if (!empty($_GET['error'])) {
        flash('error', 'Cancelaste el acceso con Google.');
        redirigir('auth/auth.php?action=login');
    }

    $state = $_GET['state'] ?? '';
    if (!is_string($state) || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
        flash('error', 'La sesión de Google expiró. Inténtalo de nuevo.');
        redirigir('auth/auth.php?action=login');
    }
    unset($_SESSION['oauth_state']);

    $codigo = limpiar($_GET['code'] ?? '', 800);
    if ($codigo === '') {
        redirigir('auth/auth.php?action=login');
    }

    // --- 1. Código → token -------------------------------------------
    $token = google_post(GOOGLE_TOKEN_URL, [
        'code'          => $codigo,
        'client_id'     => (string) env('GOOGLE_CLIENT_ID'),
        'client_secret' => (string) env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);

    if (empty($token['access_token'])) {
        flash('error', 'Google no devolvió el acceso. Revisa las credenciales en .env.');
        if (CP_DEBUG && isset($token['error_description'])) { flash('error', (string) $token['error_description']); }
        redirigir('auth/auth.php?action=login');
    }

    // --- 2. Token → perfil -------------------------------------------
    $perfil = google_get(GOOGLE_USER_URL, (string) $token['access_token']);
    $email  = $perfil['email'] ?? '';

    if ($email === '' || empty($perfil['email_verified'])) {
        flash('error', 'Google no confirmó tu correo. Usa otra cuenta o regístrate con correo y contraseña.');
        redirigir('auth/auth.php?action=login');
    }

    // --- 3. Crear o vincular en MySQL --------------------------------
    $nombre   = (string) ($perfil['name'] ?? explode('@', $email)[0]);
    $googleId = (string) ($perfil['sub'] ?? '');
    $avatar   = (string) ($perfil['picture'] ?? '');

    $st = db()->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $usuarioId = $st->fetchColumn();

    if ($usuarioId) {
        $up = db()->prepare('UPDATE usuarios SET google_id = ?, avatar_url = ? WHERE id = ?');
        $up->execute([$googleId ?: null, $avatar ?: null, $usuarioId]);
        $usuarioId = (int) $usuarioId;
    } else {
        $ins = db()->prepare(
            'INSERT INTO usuarios (nombre, email, google_id, avatar_url) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$nombre, $email, $googleId ?: null, $avatar ?: null]);
        $usuarioId = (int) db()->lastInsertId();

        $lnk = db()->prepare('UPDATE ordenes SET usuario_id = ? WHERE usuario_id IS NULL AND email_contacto = ?');
        $lnk->execute([$usuarioId, $email]);
    }

    flash('exito', '¡Bienvenido, ' . explode(' ', $nombre)[0] . '!');
    iniciar_sesion_usuario($usuarioId);
}

/** POST de formulario a Google, devuelve el JSON decodificado. */
function google_post(string $url, array $datos): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($datos),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string) $resp, true);
    return is_array($json) ? $json : [];
}

/** GET autenticado con Bearer token. */
function google_get(string $url, string $accessToken): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string) $resp, true);
    return is_array($json) ? $json : [];
}

// =====================================================================
// Pantalla compartida de login / registro
// =====================================================================
function pantalla_auth(string $modo, string $email, string $error): void
{
    $esRegistro = ($modo === 'registro');
    render_header($esRegistro ? 'Crea tu cuenta' : 'Inicia sesión', ['clase' => 'pagina-auth']);
    ?>
    <div class="login-centro">
      <svg class="pcb" viewBox="0 0 900 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <path d="M-20 140 H160 L220 200 H400"/>
        <path d="M920 460 H720 L660 400 H480"/>
        <circle cx="220" cy="200" r="4"/>
        <circle cx="660" cy="400" r="4"/>
      </svg>

      <div class="login-caja">
        <div class="panel-form">
          <h1><?= $esRegistro ? 'Crea tu cuenta' : 'Bienvenido de vuelta' ?></h1>
          <p class="apagado pequeno" style="margin-bottom:1.5rem">
            <?= $esRegistro
                ? 'Con una cuenta ves todos tus equipos juntos y recibes cada avance automáticamente.'
                : 'Entra para ver el avance de tus reparaciones.' ?>
          </p>

          <?php if ($error !== ''): ?>
            <div class="aviso aviso--error" style="width:auto;margin:0 0 1.2rem" role="alert"><?= e($error) ?></div>
          <?php endif; ?>

          <?php if (google_configurado()): ?>
            <a class="btn-google" href="<?= url('auth/auth.php?action=google') ?>">
              <svg viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.6 30.2.5 24 .5 14.6.5 6.5 5.9 2.6 13.7l7.8 6.1C12.3 13.7 17.6 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.2-.4-4.7H24v9h12.7c-.6 3-2.3 5.6-4.9 7.3l7.6 5.9c4.4-4.1 7.1-10.1 7.1-17.5z"/>
                <path fill="#FBBC05" d="M10.4 28.2a14.6 14.6 0 0 1 0-8.4l-7.8-6.1a24 24 0 0 0 0 20.6l7.8-6.1z"/>
                <path fill="#34A853" d="M24 47.5c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.4 0-11.7-4.2-13.6-10.1l-7.8 6.1C6.5 42.1 14.6 47.5 24 47.5z"/>
              </svg>
              Continuar con Google
            </a>
            <div class="separador-o">o con tu correo</div>
          <?php endif; ?>

          <form method="post" action="<?= url('auth/auth.php?action=' . ($esRegistro ? 'registro' : 'login')) ?>">
            <?= csrf_campo() ?>

            <?php if ($esRegistro): ?>
              <div class="campo">
                <label for="nombre">Tu nombre</label>
                <input type="text" id="nombre" name="nombre" required autocomplete="name">
              </div>
            <?php endif; ?>

            <div class="campo">
              <label for="email">Correo</label>
              <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
            </div>

            <?php if ($esRegistro): ?>
              <div class="campo">
                <label for="whatsapp">WhatsApp (opcional)</label>
                <input type="tel" id="whatsapp" name="whatsapp" placeholder="7299244238" autocomplete="tel">
                <p class="ayuda">Lo usamos solo para avisarte cómo va tu equipo.</p>
              </div>
            <?php endif; ?>

            <div class="campo">
              <label for="password">Contraseña</label>
              <input type="password" id="password" name="password" required
                     autocomplete="<?= $esRegistro ? 'new-password' : 'current-password' ?>"
                     <?= $esRegistro ? 'minlength="8"' : '' ?>>
              <?php if ($esRegistro): ?><p class="ayuda">Mínimo 8 caracteres.</p><?php endif; ?>
            </div>

            <button class="btn btn--rojo btn--bloque" type="submit" style="margin-top:.5rem">
              <?= $esRegistro ? 'Crear cuenta' : 'Entrar' ?>
            </button>
          </form>

          <p class="centro pequeno apagado" style="margin:1.3rem 0 0">
            <?php if ($esRegistro): ?>
              ¿Ya tienes cuenta? <a href="<?= url('auth/auth.php?action=login') ?>">Inicia sesión</a>
            <?php else: ?>
              ¿Primera vez aquí? <a href="<?= url('auth/auth.php?action=registro') ?>">Crea tu cuenta</a>
            <?php endif; ?>
          </p>
        </div>

        <p class="centro pequeno apagado" style="margin-top:1.1rem">
          <a href="<?= url('index.php') ?>">Volver al sitio</a>
        </p>
      </div>
    </div>
    <?php
    render_footer();
}
