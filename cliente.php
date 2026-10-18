<?php
/**
 * CrazyPro-Reparaciones — Panel del cliente (requiere sesión).
 *   ?view=panel   → sus equipos y estados
 *   ?view=orden   → detalle de una orden
 *   ?view=perfil  → WhatsApp, canal de aviso y tema
 *   ?view=tema    → guarda el tema por fetch (sin recargar)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

requerir_login();

$view = $_GET['view'] ?? 'panel';

switch ($view) {
    case 'panel':  vista_panel();  break;
    case 'orden':  vista_orden();  break;
    case 'perfil': vista_perfil(); break;
    case 'tema':   guardar_tema(); break;
    default:
        http_response_code(404);
        redirigir('cliente/cliente.php?view=panel');
}

// =====================================================================
function vista_panel(): void
{
    $u = usuario_actual();

    $st = db()->prepare(
        'SELECT o.*, e.nombre AS estado_nombre, e.color AS estado_color, e.es_final,
                s.nombre AS servicio_nombre
         FROM ordenes o
         JOIN estados_orden e ON e.id = o.estado_id
         LEFT JOIN servicios s ON s.id = o.servicio_id
         WHERE o.usuario_id = ?
         ORDER BY o.creado_en DESC'
    );
    $st->execute([$u['id']]);
    $ordenes = $st->fetchAll();

    $abiertas = 0;
    foreach ($ordenes as $o) {
        if (empty($o['es_final'])) { $abiertas++; }
    }

    render_header('Mis reparaciones');
    ?>
    <div class="hoja hoja--ancha">
      <div class="panel-cabeza">
        <div>
          <h1>Hola, <?= e(explode(' ', (string) $u['nombre'])[0]) ?></h1>
          <p class="apagado" style="margin:0">
            <?= $abiertas > 0
                ? ($abiertas === 1 ? 'Tienes 1 equipo en el taller.' : 'Tienes ' . $abiertas . ' equipos en el taller.')
                : 'No tienes equipos en proceso ahora mismo.' ?>
          </p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
          <a class="btn btn--plata btn--sm" href="<?= url('cliente/cliente.php?view=perfil') ?>">Mi perfil</a>
          <a class="btn btn--rojo btn--sm" href="<?= url('index.php?view=cotizar') ?>">Nueva cotización</a>
        </div>
      </div>

      <?php if (empty($u['whatsapp'])): ?>
        <div class="aviso aviso--info" style="width:auto;margin:0 0 1.5rem">
          Agrega tu número de WhatsApp en <a href="<?= url('cliente/cliente.php?view=perfil') ?>">tu perfil</a> para recibir los avisos de avance.
        </div>
      <?php endif; ?>

      <?php if (!$ordenes): ?>
        <div class="vacio">
          <h3>Todavía no tienes equipos registrados</h3>
          <p>Cuando nos traigas un equipo o mandes una cotización, aparecerá aquí con su folio y su avance.</p>
          <a class="btn btn--rojo" href="<?= url('index.php?view=cotizar') ?>">Cotizar una reparación</a>
        </div>
      <?php else: ?>
        <div class="tabla-envoltura">
          <table class="tabla">
            <thead>
              <tr>
                <th>Folio</th><th>Equipo</th><th>Servicio</th><th>Estado</th><th>Recibido</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ordenes as $o): ?>
                <tr>
                  <td class="folio"><?= e($o['folio']) ?></td>
                  <td><?= e($o['modelo'] ?: '—') ?></td>
                  <td><?= e($o['servicio_nombre'] ?: 'Por diagnosticar') ?></td>
                  <td>
                    <span class="pastilla" style="color:<?= e($o['estado_color']) ?>">
                      <span class="pastilla__punto"></span><?= e($o['estado_nombre']) ?>
                    </span>
                  </td>
                  <td class="apagado"><?= e(fecha_fmt($o['creado_en'], false)) ?></td>
                  <td>
                    <a class="btn btn--plata btn--sm" href="<?= url('cliente/cliente.php?view=orden&id=' . (int) $o['id']) ?>">Ver</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php
    render_footer();
}

// =====================================================================
function vista_orden(): void
{
    $u  = usuario_actual();
    $id = (int) ($_GET['id'] ?? 0);

    $st = db()->prepare(
        'SELECT o.*, e.nombre AS estado_nombre, e.color AS estado_color, e.es_final,
                s.nombre AS servicio_nombre
         FROM ordenes o
         JOIN estados_orden e ON e.id = o.estado_id
         LEFT JOIN servicios s ON s.id = o.servicio_id
         WHERE o.id = ? AND o.usuario_id = ? LIMIT 1'
    );
    $st->execute([$id, $u['id']]);
    $orden = $st->fetch();

    if (!$orden) {
        http_response_code(404);
        flash('error', 'Esa orden no existe o no es tuya.');
        redirigir('cliente/cliente.php?view=panel');
    }

    $historial = historial_de_orden($id);
    $piezas    = piezas_de_orden($id);
    $total     = total_orden($orden, $piezas);

    render_header('Folio ' . $orden['folio']);
    ?>
    <div class="hoja">
      <p class="pequeno" style="margin-bottom:1rem">
        <a href="<?= url('cliente/cliente.php?view=panel') ?>">← Mis reparaciones</a>
      </p>

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
          <dl style="margin:0 0 1.5rem">
            <div class="ticket__fila"><dt>Equipo</dt><dd><?= e($orden['modelo'] ?: '—') ?></dd></div>
            <?php if ($orden['capacidad']): ?>
              <div class="ticket__fila"><dt>Capacidad</dt><dd><?= e($orden['capacidad']) ?></dd></div>
            <?php endif; ?>
            <?php if ($orden['imei']): ?>
              <div class="ticket__fila"><dt>IMEI / Serie</dt><dd><?= e($orden['imei']) ?></dd></div>
            <?php endif; ?>
            <div class="ticket__fila"><dt>Servicio</dt><dd><?= e($orden['servicio_nombre'] ?: 'Por diagnosticar') ?></dd></div>
            <div class="ticket__fila"><dt>Fecha de ingreso</dt><dd><?= e(fecha_fmt($orden['creado_en'], false)) ?></dd></div>
            <?php if ($orden['fecha_promesa']): ?>
              <div class="ticket__fila"><dt>Fecha estimada</dt><dd><?= e(fecha_fmt($orden['fecha_promesa'], false)) ?></dd></div>
            <?php endif; ?>
          </dl>

          <?php if ($orden['dano_reportado']): ?>
            <div class="tarjeta" style="margin-bottom:1.2rem">
              <h3 style="font-size:.95rem">Lo que reportaste</h3>
              <p style="margin:0;font-size:.92rem"><?= nl2br(e($orden['dano_reportado'])) ?></p>
            </div>
          <?php endif; ?>

          <?php if ($orden['diagnostico']): ?>
            <div class="tarjeta" style="margin-bottom:1.5rem;border-left:3px solid var(--accent-red)">
              <h3 style="font-size:.95rem">Diagnóstico del técnico</h3>
              <p style="margin:0;font-size:.92rem"><?= nl2br(e($orden['diagnostico'])) ?></p>
            </div>
          <?php endif; ?>

          <?php if ($piezas): ?>
            <h3 style="font-size:1rem">Piezas</h3>
            <div class="tabla-envoltura" style="margin-bottom:1.5rem">
              <table class="tabla" style="min-width:auto">
                <thead><tr><th>Pieza</th><th>Tipo</th><th>Cant.</th><th>Precio</th></tr></thead>
                <tbody>
                  <?php foreach ($piezas as $p): ?>
                    <tr>
                      <td><?= e($p['nombre']) ?></td>
                      <td class="apagado"><?= e(ucfirst($p['tipo'])) ?></td>
                      <td><?= (int) $p['cantidad'] ?></td>
                      <td><?= e(precio_fmt($p['precio'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <div class="ticket__total" style="margin-bottom:1.6rem">
            <span>Total <?= (float) $orden['descuento_pct'] > 0 ? '(descuento ' . e(rtrim(rtrim(number_format((float) $orden['descuento_pct'], 2), '0'), '.')) . '% aplicado)' : '' ?></span>
            <strong><?= $total === null ? 'Por definir' : e(precio_fmt($total)) ?></strong>
          </div>

          <h3 style="font-size:1rem">Historial</h3>
          <ol class="linea-tiempo">
            <?php foreach (array_reverse($historial) as $i => $h): ?>
              <li class="<?= $i === 0 ? 'activo' : '' ?>">
                <strong><?= e($h['estado_nombre']) ?></strong>
                <span><?= e(fecha_fmt($h['creado_en'])) ?></span>
                <?php if ($h['comentario']): ?><p><?= nl2br(e($h['comentario'])) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>

          <?php if (!empty($orden['es_final'])): ?>
            <a class="btn btn--rojo" style="margin-top:1rem" target="_blank" rel="noopener"
               href="<?= e(wa_link('¡Hola! Mi folio es ' . $orden['folio'] . ' y ya está listo. Quiero coordinar la entrega.')) ?>">
              Coordinar entrega
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    render_footer();
}

// =====================================================================
function vista_perfil(): void
{
    $u = usuario_actual();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();
        $nombre   = limpiar($_POST['nombre'] ?? '', 120);
        $whatsapp = solo_digitos($_POST['whatsapp'] ?? '');
        $canal    = (string) ($_POST['canal_notif'] ?? 'ambos');
        $tema     = (string) ($_POST['tema'] ?? 'auto');

        if (!in_array($canal, ['whatsapp', 'correo', 'ambos'], true)) { $canal = 'ambos'; }
        if (!in_array($tema, ['auto', 'oscuro', 'claro'], true))      { $tema = 'auto'; }

        if ($nombre === '') {
            flash('error', 'El nombre no puede quedar vacío.');
        } elseif ($whatsapp !== '' && strlen($whatsapp) < 10) {
            flash('error', 'El WhatsApp necesita 10 dígitos.');
        } else {
            $st = db()->prepare('UPDATE usuarios SET nombre = ?, whatsapp = ?, canal_notif = ?, tema = ? WHERE id = ?');
            $st->execute([$nombre, $whatsapp ?: null, $canal, $tema, $u['id']]);
            flash('exito', 'Guardamos tus datos.');
        }
        redirigir('cliente/cliente.php?view=perfil');
    }

    render_header('Mi perfil');
    ?>
    <div class="hoja">
      <p class="pequeno" style="margin-bottom:1rem">
        <a href="<?= url('cliente/cliente.php?view=panel') ?>">← Mis reparaciones</a>
      </p>

      <div class="hoja__intro">
        <h1>Mi perfil</h1>
        <p class="apagado">Así te avisamos cuando tu equipo avanza.</p>
      </div>

      <form class="panel-form" method="post" action="<?= url('cliente/cliente.php?view=perfil') ?>">
        <?= csrf_campo() ?>

        <div class="campo">
          <label for="nombre">Nombre</label>
          <input type="text" id="nombre" name="nombre" value="<?= e($u['nombre']) ?>" required>
        </div>

        <div class="campo">
          <label for="email-fijo">Correo</label>
          <input type="email" id="email-fijo" value="<?= e($u['email']) ?>" disabled>
          <p class="ayuda">El correo es tu identificador de cuenta y no se puede cambiar aquí.</p>
        </div>

        <div class="campos-2">
          <div class="campo">
            <label for="whatsapp">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" value="<?= e((string) $u['whatsapp']) ?>" placeholder="7299244238">
          </div>
          <div class="campo">
            <label for="canal_notif">Cómo quieres que te avisemos</label>
            <select id="canal_notif" name="canal_notif">
              <option value="ambos"    <?= $u['canal_notif'] === 'ambos' ? 'selected' : '' ?>>WhatsApp y correo</option>
              <option value="whatsapp" <?= $u['canal_notif'] === 'whatsapp' ? 'selected' : '' ?>>Solo WhatsApp</option>
              <option value="correo"   <?= $u['canal_notif'] === 'correo' ? 'selected' : '' ?>>Solo correo</option>
            </select>
          </div>
        </div>

        <div class="campo">
          <label for="tema">Tema del sitio</label>
          <select id="tema" name="tema">
            <option value="auto"   <?= $u['tema'] === 'auto' ? 'selected' : '' ?>>Seguir a mi sistema</option>
            <option value="oscuro" <?= $u['tema'] === 'oscuro' ? 'selected' : '' ?>>Siempre oscuro</option>
            <option value="claro"  <?= $u['tema'] === 'claro' ? 'selected' : '' ?>>Siempre claro</option>
          </select>
        </div>

        <button class="btn btn--rojo" type="submit">Guardar cambios</button>
      </form>
    </div>
    <?php
    render_footer();
}

// =====================================================================
/** Guarda el tema desde el toggle de la barra, sin recargar. */
function guardar_tema(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false]);
        exit;
    }
    csrf_verificar();

    $tema = (string) ($_POST['tema'] ?? 'auto');
    if ($tema === 'oscuro' || $tema === 'claro') {
        $st = db()->prepare('UPDATE usuarios SET tema = ? WHERE id = ?');
        $st->execute([$tema, $_SESSION['usuario_id']]);
    }
    echo json_encode(['ok' => true, 'tema' => $tema]);
    exit;
}
