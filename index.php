<?php
/**
 * CrazyPro-Reparaciones — Router público.
 *   index.php                → landing
 *   index.php?view=cotizar   → cotización / agenda de cita
 *   index.php?view=status    → consulta pública por folio
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notificaciones.php';

$view = $_GET['view'] ?? 'landing';

switch ($view) {
    case 'cotizar': vista_cotizar(); break;
    case 'status':  vista_status();  break;
    case 'landing': vista_landing(); break;
    default:
        http_response_code(404);
        vista_404();
}

// =====================================================================
// LANDING
// =====================================================================
function vista_landing(): void
{
    $servicios = db()->query(
        'SELECT * FROM servicios WHERE activo = 1 ORDER BY categoria, posicion, id'
    )->fetchAll();

    $totalOrdenes = (int) db()->query('SELECT COUNT(*) FROM ordenes')->fetchColumn();
    $categorias   = categorias_servicios();

    render_header('', ['clase' => 'pagina-landing']);
    ?>

    <section class="hero">
      <svg class="pcb pcb--anim" viewBox="0 0 1200 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <path d="M-20 120 H180 L240 180 H420 L470 130 H700"/>
        <path d="M-20 420 H140 L210 350 H380 L430 400 H660 L720 340 H980"/>
        <path d="M1220 90 H1020 L960 150 H820"/>
        <path d="M1220 500 H1040 L980 440 H840 L790 490 H620"/>
        <circle cx="240" cy="180" r="4"/>
        <circle cx="470" cy="130" r="4"/>
        <circle cx="210" cy="350" r="4"/>
        <circle cx="720" cy="340" r="4"/>
        <circle cx="960" cy="150" r="4"/>
        <circle cx="980" cy="440" r="4"/>
      </svg>

      <div class="contenedor hero__grid">
        <div>
          <p class="hero__etiqueta"><?= e(contenido('hero_etiqueta', 'Huehuetoca, Estado de México')) ?></p>

          <h1><?= nl2br(e(contenido('hero_titulo', "Tu equipo no está muerto.\nTodavía no lo hemos tocado."))) ?></h1>

          <p class="hero__sub"><?= e(contenido('hero_subtitulo', 'Reparación y optimización de PC, laptop, Android e iPhone. Diagnóstico honesto, piezas que sí duran y un folio para seguir tu equipo paso a paso.')) ?></p>

          <div class="hero__ctas">
            <a class="btn btn--rojo" href="<?= url('index.php?view=cotizar') ?>">Cotiza tu reparación</a>
            <a class="btn btn--plata" href="#servicios">Ver todos los servicios</a>
          </div>

          <div class="hero__datos">
            <div class="hero__dato">
              <strong><?= e(contenido('dato_1_valor', '24 h')) ?></strong>
              <span><?= e(contenido('dato_1_texto', 'Diagnóstico promedio')) ?></span>
            </div>
            <div class="hero__dato">
              <strong><?= e(contenido('dato_2_valor', '90 días')) ?></strong>
              <span><?= e(contenido('dato_2_texto', 'Garantía en piezas')) ?></span>
            </div>
            <div class="hero__dato">
              <strong><?= $totalOrdenes > 0 ? e((string) $totalOrdenes) : e(contenido('dato_3_valor', '4')) ?></strong>
              <span><?= $totalOrdenes > 0 ? 'Equipos atendidos' : e(contenido('dato_3_texto', 'Especialidades')) ?></span>
            </div>
          </div>
        </div>

        <!-- El buscador de folio es la pieza interactiva de la portada -->
        <div class="hero__buscador">
          <h2>¿Ya dejaste tu equipo?</h2>
          <p>Escribe tu folio y mira exactamente en qué va, sin crear cuenta.</p>
          <form action="<?= url('index.php') ?>" method="get">
            <input type="hidden" name="view" value="status">
            <label class="solo-lectores" for="folio-hero">Folio de tu ticket</label>
            <input type="text" id="folio-hero" name="folio" data-folio placeholder="16Sep2026-01" required>
            <button class="btn btn--rojo" type="submit">Consultar</button>
          </form>
          <p class="ayuda" style="margin-top:.9rem">El folio viene impreso en el ticket que te entregamos al recibir tu equipo.</p>
        </div>
      </div>
    </section>

    <!-- Catálogo -->
    <section class="seccion" id="servicios">
      <div class="contenedor">
        <div class="titulo-seccion">
          <h2><?= e(contenido('servicios_titulo', 'Qué reparamos')) ?></h2>
        </div>
        <p class="apagado" style="margin-top:-1.4rem;margin-bottom:2rem;max-width:60ch">
          <?= e(contenido('servicios_intro', 'Precios claros. Si un servicio dice “Precio por definir” es porque depende del diagnóstico: te lo confirmamos antes de tocar nada.')) ?>
        </p>

        <div class="cat-tabs" role="group" aria-label="Filtrar servicios por categoría">
          <button class="cat-tab activa" type="button" data-cat="todos">Todos</button>
          <?php foreach ($categorias as $clave => $etiqueta): ?>
            <button class="cat-tab" type="button" data-cat="<?= e($clave) ?>"><?= e($etiqueta) ?></button>
          <?php endforeach; ?>
        </div>

        <?php if (!$servicios): ?>
          <div class="vacio">
            <h3>Todavía no hay servicios publicados</h3>
            <p>Ejecuta <code>database/seed.php</code> para cargar el catálogo inicial.</p>
          </div>
        <?php else: ?>
          <div class="rejilla-servicios">
            <?php foreach ($servicios as $s): ?>
              <article class="servicio aparece" data-cat="<?= e($s['categoria']) ?>">
                <?php if (!empty($s['imagen'])): ?>
                  <img class="servicio__img" src="<?= e(img_url($s['imagen'])) ?>" alt="" loading="lazy">
                <?php endif; ?>
                <h3><?= e($s['nombre']) ?></h3>
                <?php if (!empty($s['descripcion'])): ?>
                  <p><?= e($s['descripcion']) ?></p>
                <?php endif; ?>
                <div class="servicio__pie">
                  <span class="<?= $s['precio'] === null ? 'servicio__precio servicio__precio--indef' : 'servicio__precio' ?>">
                    <?= $s['precio'] === null ? 'Precio por definir' : e(precio_fmt($s['precio'])) ?>
                  </span>
                  <a class="btn btn--plata btn--sm" href="<?= url('index.php?view=cotizar&servicio=' . (int) $s['id']) ?>">Cotizar</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <hr class="pista">

    <!-- Cómo funciona: sí es una secuencia, por eso va numerada -->
    <section class="seccion">
      <div class="contenedor">
        <div class="titulo-seccion"><h2>Cómo funciona</h2></div>
        <div class="pasos">
          <div class="paso aparece">
            <h3>Cotizas</h3>
            <p>Nos cuentas qué equipo tienes y qué le pasa. Te respondemos por WhatsApp o correo.</p>
          </div>
          <div class="paso aparece">
            <h3>Recibimos tu equipo</h3>
            <p>Te damos un folio impreso. Ese folio es tu llave para seguir todo el proceso.</p>
          </div>
          <div class="paso aparece">
            <h3>Reparamos</h3>
            <p>Cada cambio de estado te llega como aviso, con la nota del técnico y el costo confirmado.</p>
          </div>
          <div class="paso aparece">
            <h3>Lo recoges o te lo llevamos</h3>
            <p>Cuando esté listo eliges: pasas al taller o lo enviamos a domicilio.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="seccion">
      <div class="contenedor">
        <div class="cta-franja aparece">
          <div>
            <h2><?= e(contenido('cta_titulo', 'Dinos qué le pasa a tu equipo')) ?></h2>
            <p><?= e(contenido('cta_texto', 'Respondemos el mismo día. Sin compromiso y sin letras chiquitas.')) ?></p>
          </div>
          <div style="display:flex;gap:.7rem;flex-wrap:wrap">
            <a class="btn btn--rojo" href="<?= url('index.php?view=cotizar') ?>">Cotiza ahora</a>
            <a class="btn btn--plata" target="_blank" rel="noopener"
               href="<?= e(wa_link(contenido('wa_msg_generico', '¡Hola! Quiero más información sobre sus servicios.'))) ?>">
              Escribir por WhatsApp
            </a>
          </div>
        </div>
      </div>
    </section>

    <?php
    render_footer();
}

// =====================================================================
// COTIZAR / AGENDAR
// =====================================================================
function vista_cotizar(): void
{
    $errores = [];
    $datos = [
        'nombre'      => '',
        'email'       => '',
        'telefono'    => '',
        'tipo_equipo' => 'otro',
        'modelo'      => '',
        'capacidad'   => '',
        'servicio_id' => (string) ($_GET['servicio'] ?? ''),
        'descripcion' => '',
        'codigo'      => '',
        'canal'       => 'ambos',
    ];

    if ($u = usuario_actual()) {
        $datos['nombre']   = $u['nombre'];
        $datos['email']    = $u['email'];
        $datos['telefono'] = (string) ($u['whatsapp'] ?? '');
        $datos['canal']    = $u['canal_notif'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();

        foreach (array_keys($datos) as $k) {
            $datos[$k] = limpiar($_POST[$k] ?? '', 2000);
        }

        if ($datos['nombre'] === '')                                        { $errores['nombre'] = 'Escribe tu nombre.'; }
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL))            { $errores['email'] = 'Revisa tu correo: no parece válido.'; }
        if (strlen(solo_digitos($datos['telefono'])) < 10)                  { $errores['telefono'] = 'Necesitamos 10 dígitos para avisarte por WhatsApp.'; }
        if ($datos['descripcion'] === '')                                   { $errores['descripcion'] = 'Cuéntanos qué le pasa al equipo.'; }
        if (!in_array($datos['tipo_equipo'], ['pc','laptop','android','iphone','otro'], true)) { $datos['tipo_equipo'] = 'otro'; }
        if (!in_array($datos['canal'], ['whatsapp','correo','ambos'], true)) { $datos['canal'] = 'ambos'; }

        // Código de aliado (opcional)
        $aliado = null;
        if ($datos['codigo'] !== '') {
            $st = db()->prepare('SELECT * FROM aliados WHERE codigo = ? AND activo = 1 LIMIT 1');
            $st->execute([strtoupper($datos['codigo'])]);
            $aliado = $st->fetch() ?: null;
            if (!$aliado) {
                $errores['codigo'] = 'Ese código no existe o ya no está activo.';
            }
        }

        if (!$errores) {
            $pdo = db();
            try {
                $pdo->beginTransaction();

                // Vincula con la cuenta si ya existe ese correo.
                $usuarioId = $_SESSION['usuario_id'] ?? null;
                if (!$usuarioId) {
                    $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
                    $st->execute([$datos['email']]);
                    $encontrado = $st->fetchColumn();
                    $usuarioId = $encontrado ? (int) $encontrado : null;
                }

                $folio = generar_folio();
                $st = $pdo->prepare(
                    'INSERT INTO ordenes
                        (folio, usuario_id, servicio_id, estado_id, aliado_id, tipo_equipo, modelo,
                         capacidad, dano_reportado, descuento_pct, nombre_contacto, email_contacto,
                         tel_contacto, origen, revisada)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "web", 0)'
                );
                $st->execute([
                    $folio,
                    $usuarioId,
                    ctype_digit($datos['servicio_id']) && (int) $datos['servicio_id'] > 0
                        ? (int) $datos['servicio_id'] : null,
                    estado_inicial_id(),
                    $aliado ? (int) $aliado['id'] : null,
                    $datos['tipo_equipo'],
                    $datos['modelo'] !== '' ? $datos['modelo'] : null,
                    $datos['capacidad'] !== '' ? $datos['capacidad'] : null,
                    $datos['descripcion'],
                    $aliado ? (float) $aliado['descuento_pct'] : 0,
                    $datos['nombre'],
                    $datos['email'],
                    solo_digitos($datos['telefono']),
                ]);
                $ordenId = (int) $pdo->lastInsertId();

                // Primer registro del historial.
                $st = $pdo->prepare(
                    'INSERT INTO historial_estados (orden_id, estado_id, comentario)
                     VALUES (?, ?, ?)'
                );
                $st->execute([$ordenId, estado_inicial_id(), 'Solicitud recibida desde el sitio web.']);

                // Referido automático por código.
                if ($aliado) {
                    $st = $pdo->prepare(
                        'INSERT INTO referidos (aliado_id, orden_id, origen, comision, pagado, nota)
                         VALUES (?, ?, "codigo", ?, 0, ?)'
                    );
                    $st->execute([
                        (int) $aliado['id'],
                        $ordenId,
                        (float) $aliado['comision'],
                        'Código usado en la cotización web',
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                flash('error', 'No pudimos guardar tu solicitud. Inténtalo otra vez o escríbenos por WhatsApp.');
                if (CP_DEBUG) { flash('error', $ex->getMessage()); }
                redirigir('index.php?view=cotizar');
            }

            // Aviso de recepción (si el correo del servidor está configurado).
            $orden = orden_por_folio($folio);
            if ($orden) { notificar_cotizacion_recibida($orden); }

            redirigir('index.php?view=status&folio=' . rawurlencode($folio) . '&nueva=1');
        }
    }

    $servicios = db()->query(
        'SELECT id, nombre, categoria FROM servicios WHERE activo = 1 ORDER BY categoria, posicion, id'
    )->fetchAll();
    $categorias = categorias_servicios();

    render_header('Cotiza tu reparación');
    ?>
    <div class="hoja">
      <div class="hoja__intro">
        <h1>Cotiza tu reparación</h1>
        <p class="apagado">Llena esto en un minuto. Te damos folio de inmediato y te contactamos con el costo antes de tocar el equipo.</p>
      </div>

      <?php if ($errores): ?>
        <div class="aviso aviso--error" style="width:auto;margin:0 0 1.4rem" role="alert">
          Revisa los campos marcados y vuelve a enviar.
        </div>
      <?php endif; ?>

      <form class="panel-form" method="post" action="<?= url('index.php?view=cotizar') ?>" novalidate>
        <?= csrf_campo() ?>

        <div class="campos-2">
          <div class="campo">
            <label for="nombre">Tu nombre</label>
            <input type="text" id="nombre" name="nombre" value="<?= e($datos['nombre']) ?>" required>
            <?php if (isset($errores['nombre'])): ?><p class="error-campo"><?= e($errores['nombre']) ?></p><?php endif; ?>
          </div>
          <div class="campo">
            <label for="telefono">WhatsApp (10 dígitos)</label>
            <input type="tel" id="telefono" name="telefono" value="<?= e($datos['telefono']) ?>" placeholder="7299244238" required>
            <?php if (isset($errores['telefono'])): ?><p class="error-campo"><?= e($errores['telefono']) ?></p><?php endif; ?>
          </div>
        </div>

        <div class="campo">
          <label for="email">Correo</label>
          <input type="email" id="email" name="email" value="<?= e($datos['email']) ?>" required>
          <?php if (isset($errores['email'])): ?><p class="error-campo"><?= e($errores['email']) ?></p><?php endif; ?>
        </div>

        <div class="campos-3">
          <div class="campo">
            <label for="tipo_equipo">Tipo de equipo</label>
            <select id="tipo_equipo" name="tipo_equipo">
              <?php foreach (['pc' => 'PC de escritorio', 'laptop' => 'Laptop', 'android' => 'Celular Android', 'iphone' => 'iPhone', 'otro' => 'Otro'] as $v => $t): ?>
                <option value="<?= e($v) ?>" <?= $datos['tipo_equipo'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="modelo">Marca y modelo</label>
            <input type="text" id="modelo" name="modelo" value="<?= e($datos['modelo']) ?>" placeholder="iPhone 12 Pro">
          </div>
          <div class="campo">
            <label for="capacidad">Capacidad / RAM</label>
            <input type="text" id="capacidad" name="capacidad" value="<?= e($datos['capacidad']) ?>" placeholder="128 GB">
          </div>
        </div>

        <div class="campo">
          <label for="servicio_id">Servicio que te interesa</label>
          <select id="servicio_id" name="servicio_id">
            <option value="">Todavía no sé / que lo diagnostiquen</option>
            <?php foreach ($categorias as $clave => $etiqueta): ?>
              <optgroup label="<?= e($etiqueta) ?>">
                <?php foreach ($servicios as $s): if ($s['categoria'] !== $clave) { continue; } ?>
                  <option value="<?= (int) $s['id'] ?>" <?= (string) $datos['servicio_id'] === (string) $s['id'] ? 'selected' : '' ?>>
                    <?= e($s['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="descripcion">¿Qué le pasa?</label>
          <textarea id="descripcion" name="descripcion" required placeholder="Se cayó y la pantalla quedó estrellada; el táctil sí responde."><?= e($datos['descripcion']) ?></textarea>
          <?php if (isset($errores['descripcion'])): ?><p class="error-campo"><?= e($errores['descripcion']) ?></p><?php endif; ?>
        </div>

        <div class="campos-2">
          <div class="campo">
            <label for="codigo">Código de aliado (opcional)</label>
            <input type="text" id="codigo" name="codigo" value="<?= e($datos['codigo']) ?>" placeholder="TIENDAGONZALES" style="text-transform:uppercase">
            <p class="ayuda">¿Te recomendó un negocio? Escribe su código y aplicamos su descuento.</p>
            <?php if (isset($errores['codigo'])): ?><p class="error-campo"><?= e($errores['codigo']) ?></p><?php endif; ?>
          </div>
          <div class="campo">
            <label for="canal">¿Cómo te avisamos?</label>
            <select id="canal" name="canal">
              <option value="ambos"    <?= $datos['canal'] === 'ambos' ? 'selected' : '' ?>>WhatsApp y correo</option>
              <option value="whatsapp" <?= $datos['canal'] === 'whatsapp' ? 'selected' : '' ?>>Solo WhatsApp</option>
              <option value="correo"   <?= $datos['canal'] === 'correo' ? 'selected' : '' ?>>Solo correo</option>
            </select>
          </div>
        </div>

        <button class="btn btn--rojo btn--bloque" type="submit" style="margin-top:.6rem">Enviar y obtener mi folio</button>
        <p class="ayuda centro" style="margin-top:.9rem">Al enviar aceptas que te contactemos por el medio que elegiste.</p>
      </form>
    </div>
    <?php
    render_footer();
}

// =====================================================================
// CONSULTA PÚBLICA DE STATUS
// =====================================================================
function vista_status(): void
{
    $folio  = limpiar($_GET['folio'] ?? '', 20);
    $nueva  = !empty($_GET['nueva']);
    $orden  = $folio !== '' ? orden_por_folio($folio) : null;
    $noHay  = ($folio !== '' && !$orden);

    render_header('Consulta tu status');
    ?>
    <div class="hoja">
      <div class="hoja__intro">
        <h1><?= $nueva && $orden ? '¡Listo! Tu folio está activo' : 'Consulta tu status' ?></h1>
        <p class="apagado">
          <?= $nueva && $orden
              ? 'Guarda este folio: con él puedes revisar el avance cuando quieras, sin cuenta ni contraseña.'
              : 'Escribe el folio que viene en tu ticket y te decimos exactamente en qué va tu equipo.' ?>
        </p>
      </div>

      <form class="panel-form" method="get" action="<?= url('index.php') ?>" style="margin-bottom:1.6rem">
        <input type="hidden" name="view" value="status">
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
          <div style="flex:1 1 220px">
            <label for="folio">Folio</label>
            <input type="text" id="folio" name="folio" data-folio value="<?= e($folio) ?>" placeholder="16Sep2026-01" required>
          </div>
          <button class="btn btn--rojo" type="submit" style="align-self:flex-end">Consultar</button>
        </div>
      </form>

      <?php if ($noHay): ?>
        <div class="vacio">
          <h3>No encontramos ese folio</h3>
          <p>Revisa que esté escrito igual que en tu ticket, incluyendo el guion. Si sigue sin aparecer, escríbenos por WhatsApp y lo buscamos contigo.</p>
          <a class="btn btn--plata" target="_blank" rel="noopener"
             href="<?= e(wa_link('¡Hola! Estoy consultando el folio ' . $folio . ' y no aparece en el sitio.')) ?>">Escribir por WhatsApp</a>
        </div>

      <?php elseif ($orden):
        $historial = historial_de_orden((int) $orden['id']);
        $piezas    = piezas_de_orden((int) $orden['id']);
        $total     = total_orden($orden, $piezas);
        $estados   = estados_lista();
        ?>
        <div class="status-tarjeta">
          <div class="status-cabeza">
            <div>
              <p class="pequeno apagado" style="margin:0">Folio</p>
              <p class="status-folio" style="margin:0"><?= e($orden['folio']) ?></p>
            </div>
            <span class="pastilla" style="color:<?= e($orden['estado_color']) ?>">
              <span class="pastilla__punto"></span><?= e($orden['estado_nombre']) ?>
            </span>
          </div>

          <div class="status-cuerpo">
            <dl style="margin:0 0 1.6rem">
              <div class="ticket__fila"><dt>Equipo</dt><dd><?= e($orden['modelo'] ?: '—') ?></dd></div>
              <?php if ($orden['servicio_nombre']): ?>
                <div class="ticket__fila"><dt>Servicio</dt><dd><?= e($orden['servicio_nombre']) ?></dd></div>
              <?php endif; ?>
              <div class="ticket__fila"><dt>Recibido</dt><dd><?= e(fecha_fmt($orden['creado_en'], false)) ?></dd></div>
              <?php if ($orden['fecha_promesa']): ?>
                <div class="ticket__fila"><dt>Fecha estimada</dt><dd><?= e(fecha_fmt($orden['fecha_promesa'], false)) ?></dd></div>
              <?php endif; ?>
              <div class="ticket__fila">
                <dt>Costo</dt>
                <dd><?= $total === null ? 'Precio por definir' : e(precio_fmt($total)) ?></dd>
              </div>
            </dl>

            <?php if ($orden['diagnostico']): ?>
              <div class="tarjeta" style="margin-bottom:1.6rem">
                <h3 style="font-size:.95rem">Diagnóstico del técnico</h3>
                <p style="margin:0;font-size:.92rem"><?= nl2br(e($orden['diagnostico'])) ?></p>
              </div>
            <?php endif; ?>

            <h3 style="font-size:1rem">Avance</h3>
            <ol class="linea-tiempo">
              <?php if ($historial): foreach (array_reverse($historial) as $i => $h): ?>
                <li class="<?= $i === 0 ? 'activo' : '' ?>">
                  <strong><?= e($h['estado_nombre']) ?></strong>
                  <span><?= e(fecha_fmt($h['creado_en'])) ?></span>
                  <?php if ($h['comentario']): ?><p><?= nl2br(e($h['comentario'])) ?></p><?php endif; ?>
                </li>
              <?php endforeach; else: ?>
                <li class="activo">
                  <strong><?= e($orden['estado_nombre']) ?></strong>
                  <span><?= e(fecha_fmt($orden['creado_en'])) ?></span>
                </li>
              <?php endif; ?>
            </ol>

            <?php if (!empty($orden['es_final'])): ?>
              <div class="tarjeta" style="margin-top:1.4rem;border-color:var(--accent-red)">
                <h3 style="font-size:.98rem">Tu equipo ya está listo</h3>
                <p style="font-size:.92rem">Puedes pasar al taller en <?= e(contenido('direccion', 'Huehuetoca, Estado de México')) ?>, o pedirnos envío a domicilio.</p>
                <a class="btn btn--rojo btn--sm" target="_blank" rel="noopener"
                   href="<?= e(wa_link('¡Hola! Mi folio es ' . $orden['folio'] . ' y ya está listo. Quiero coordinar la entrega.')) ?>">Coordinar entrega</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <p class="centro pequeno apagado" style="margin-top:1.4rem">
          <?php if (!esta_logueado()): ?>
            <a href="<?= url('auth/auth.php?action=registro') ?>">Crea tu cuenta</a> para ver todos tus equipos juntos y recibir avisos automáticos.
          <?php else: ?>
            <a href="<?= url('cliente/cliente.php?view=panel') ?>">Ver todas mis reparaciones</a>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
    <?php
    render_footer();
}

// =====================================================================
function vista_404(): void
{
    render_header('Página no encontrada');
    ?>
    <div class="hoja">
      <div class="vacio">
        <h3>Esta página no existe</h3>
        <p>El enlace puede estar mal escrito o la página se movió.</p>
        <a class="btn btn--rojo" href="<?= url('index.php') ?>">Volver al inicio</a>
      </div>
    </div>
    <?php
    render_footer();
}
