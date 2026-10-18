<?php
/**
 * CrazyPro-Reparaciones — Layout compartido.
 * render_header() y render_footer(): nav según sesión, toggle de tema,
 * botón flotante de WhatsApp y motivo de circuito de fondo.
 */

declare(strict_types=1);

function render_header(string $titulo = '', array $opciones = []): void
{
    $marca      = contenido('sitio_nombre', 'CrazyPro-Reparaciones');
    $tituloFull = $titulo !== '' ? $titulo . ' · ' . $marca : $marca . ' · ' . contenido('sitio_tagline', 'Reparación de PC, Android e iPhone');
    $desc       = $opciones['descripcion'] ?? contenido('sitio_descripcion', 'Taller de reparación y optimización de computadoras, celulares Android e iPhone en Huehuetoca, Estado de México.');
    $clase      = $opciones['clase'] ?? '';
    $u          = usuario_actual();
    $temaPref   = $u['tema'] ?? 'auto';
    ?>
<!DOCTYPE html>
<html lang="es" data-tema-pref="<?= e($temaPref) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($tituloFull) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#0A0A0B">
<link rel="icon" href="<?= url('assets/logo.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;700;800;900&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>?v=1">
<script>
/* Aplica el tema antes de pintar, para evitar el parpadeo blanco. */
(function () {
  try {
    var pref = document.documentElement.getAttribute('data-tema-pref');
    var guardado = localStorage.getItem('cp_tema');
    var tema = (guardado && guardado !== 'auto') ? guardado
             : (pref && pref !== 'auto') ? pref
             : (window.matchMedia('(prefers-color-scheme: light)').matches ? 'claro' : 'oscuro');
    document.documentElement.setAttribute('data-tema', tema);
  } catch (e) {
    document.documentElement.setAttribute('data-tema', 'oscuro');
  }
})();
</script>
</head>
<body class="<?= e($clase) ?>"
      data-base="<?= e(CP_URL) ?>"
      data-csrf="<?= e(csrf_token()) ?>"
      data-logueado="<?= esta_logueado() ? '1' : '0' ?>">

<a class="saltar" href="#principal">Saltar al contenido</a>

<header class="barra">
  <div class="barra__inner">
    <a class="marca" href="<?= url('index.php') ?>">
      <img src="<?= img_url(contenido('logo_url'), 'assets/logo.svg') ?>" alt="" width="38" height="38">
      <span class="marca__txt"><?= e($marca) ?></span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-principal" aria-label="Abrir menú">
      <span></span><span></span><span></span>
    </button>

    <nav id="nav-principal" class="nav" aria-label="Navegación principal">
      <a href="<?= url('index.php#servicios') ?>">Servicios</a>
      <a href="<?= url('index.php?view=status') ?>">Consulta tu status</a>
      <?php if (esta_logueado()): ?>
        <a href="<?= url('cliente/cliente.php?view=panel') ?>">Mis reparaciones</a>
        <a href="<?= url('auth/auth.php?action=logout') ?>" class="nav__sec">Cerrar sesión</a>
      <?php else: ?>
        <a href="<?= url('auth/auth.php?action=login') ?>" class="nav__sec">Iniciar sesión</a>
      <?php endif; ?>
      <a class="btn btn--rojo btn--sm" href="<?= url('index.php?view=cotizar') ?>">Cotiza ahora</a>
      <button class="tema-btn" type="button" id="tema-toggle" aria-label="Cambiar entre modo claro y oscuro" title="Cambiar tema">
        <svg viewBox="0 0 24 24" aria-hidden="true" class="ico-luna"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
        <svg viewBox="0 0 24 24" aria-hidden="true" class="ico-sol"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2.6M12 19.4V22M2 12h2.6M19.4 12H22M4.9 4.9l1.9 1.9M17.2 17.2l1.9 1.9M19.1 4.9l-1.9 1.9M6.8 17.2l-1.9 1.9"/></svg>
      </button>
    </nav>
  </div>
</header>

<?php foreach (flash() as $f): ?>
  <div class="aviso aviso--<?= e($f['tipo']) ?>" role="status"><?= e($f['mensaje']) ?></div>
<?php endforeach; ?>

<main id="principal">
<?php
}

function render_footer(array $opciones = []): void
{
    $marca = contenido('sitio_nombre', 'CrazyPro-Reparaciones');
    $tel   = contenido('telefono_publico', '729-924-4238');
    $dir   = contenido('direccion', 'Huehuetoca, Estado de México');
    $correo = contenido('correo_publico', 'contacto@crazypro-reparaciones.com');
    $horario = contenido('horario', 'Lunes a sábado, 10:00 – 19:00');
    ?>
</main>

<footer class="pie">
  <div class="pie__inner">
    <div class="pie__col">
      <img src="<?= img_url(contenido('logo_url'), 'assets/logo.svg') ?>" alt="" width="44" height="44">
      <p class="pie__lema"><?= e(contenido('sitio_tagline', 'Reparamos, optimizamos y devolvemos a la vida tu equipo.')) ?></p>
    </div>
    <div class="pie__col">
      <h3>Contacto</h3>
      <p><a href="<?= e(wa_link(contenido('wa_msg_generico', '¡Hola! Quiero más información sobre sus servicios.'))) ?>" target="_blank" rel="noopener"><?= e($tel) ?></a></p>
      <p><a href="mailto:<?= e($correo) ?>"><?= e($correo) ?></a></p>
      <p><?= e($dir) ?></p>
      <p><?= e($horario) ?></p>
    </div>
    <div class="pie__col">
      <h3>Sitio</h3>
      <p><a href="<?= url('index.php#servicios') ?>">Catálogo de servicios</a></p>
      <p><a href="<?= url('index.php?view=cotizar') ?>">Cotizar una reparación</a></p>
      <p><a href="<?= url('index.php?view=status') ?>">Consulta tu status</a></p>
      <p><a href="<?= url('auth/auth.php?action=login') ?>">Acceso de clientes</a></p>
    </div>
    <div class="pie__col">
      <h3>Política</h3>
      <p class="pie__nota"><?= e(contenido('politica_desbloqueo', 'No realizamos desbloqueo de equipos robados ni retiro de cuentas de procedencia dudosa.')) ?></p>
    </div>
  </div>
  <p class="pie__legal">© <?= date('Y') ?> <?= e($marca) ?>. Hecho en Huehuetoca.</p>
</footer>

<!-- Botón flotante de WhatsApp -->
<div class="wa-flotante" id="wa-flotante">
  <button class="wa-btn" type="button" id="wa-abrir" aria-expanded="false" aria-controls="wa-panel" aria-label="Escríbenos por WhatsApp">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.6 2 2.2 6.4 2.2 11.84c0 1.9.5 3.66 1.4 5.2L2 22l5.1-1.55a9.8 9.8 0 0 0 4.94 1.33h.01c5.43 0 9.84-4.4 9.84-9.84C21.9 6.4 17.48 2 12.04 2Zm5.76 13.9c-.24.68-1.4 1.3-1.94 1.35-.5.05-.98.23-3.3-.7-2.78-1.1-4.54-3.95-4.68-4.13-.14-.18-1.12-1.49-1.12-2.84 0-1.35.7-2.01.95-2.29.25-.28.55-.35.73-.35h.52c.17 0 .4-.06.62.48.24.57.8 1.97.87 2.11.07.14.12.3.02.49-.1.18-.14.29-.28.45-.14.16-.3.36-.42.48-.14.14-.29.29-.12.57.16.28.73 1.2 1.56 1.94 1.07.95 1.98 1.25 2.26 1.39.28.14.44.12.6-.07.17-.2.7-.81.88-1.09.18-.28.36-.23.6-.14.25.09 1.57.74 1.84.87.27.14.45.21.51.32.07.11.07.64-.17 1.31Z"/></svg>
  </button>

  <div class="wa-panel" id="wa-panel" hidden>
    <div class="wa-panel__head">
      <strong>Escríbenos por WhatsApp</strong>
      <button type="button" class="wa-cerrar" id="wa-cerrar" aria-label="Cerrar">×</button>
    </div>

    <div class="wa-opciones">
      <a class="btn btn--rojo btn--bloque" target="_blank" rel="noopener"
         href="<?= e(wa_link(contenido('wa_msg_generico', '¡Hola! Quiero más información sobre sus servicios.'))) ?>">
        Enviar mensaje rápido
      </a>
      <p class="wa-o">o arma tu mensaje</p>
    </div>

    <div class="wa-form">
      <label for="wa-equipo">Equipo</label>
      <input type="text" id="wa-equipo" placeholder="iPhone 12 Pro de 128 GB" autocomplete="off">

      <label for="wa-problema">Problema</label>
      <input type="text" id="wa-problema" placeholder="la pantalla está estrellada" autocomplete="off">

      <label for="wa-pregunta">Tu pregunta</label>
      <select id="wa-pregunta">
        <option value="¿cuándo podrían repararlo y cuánto costaría?">Cuándo y cuánto cuesta</option>
        <option value="¿tienen la pieza en existencia?">Si hay pieza en existencia</option>
        <option value="¿cuánto tardaría la reparación?">Cuánto tarda</option>
        <option value="¿pueden recogerlo a domicilio?">Si recogen a domicilio</option>
      </select>

      <p class="wa-vista" id="wa-vista" aria-live="polite"></p>
      <a class="btn btn--plata btn--bloque" id="wa-enviar" target="_blank" rel="noopener" href="#">Abrir WhatsApp con este mensaje</a>
    </div>
  </div>
</div>

<script>window.CP_WA_NUMERO = <?= json_encode(wa_numero()) ?>;</script>
<script src="<?= url('js/main.js') ?>?v=1" defer></script>
</body>
</html>
<?php
}
