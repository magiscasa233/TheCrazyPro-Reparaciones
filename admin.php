<?php
/**
 * CrazyPro-Reparaciones — Router de administrador.
 *
 * ?view=dashboard     Pendientes del día y resumen
 * ?view=ordenes       Listado, filtros y alta de órdenes
 * ?view=orden_editar  Edición, cambio de estado, piezas
 * ?view=ticket        Comprobante y etiqueta imprimibles
 * ?view=clientes      Clientes registrados
 * ?view=servicios     Catálogo (CMS)
 * ?view=estados       Estados de orden (CMS)
 * ?view=contenido     Textos, imágenes y ajustes del sitio (CMS)
 * ?view=aliados       Programa de referidos
 *
 * Una función por vista: aunque el archivo sea largo, cada pantalla vive
 * en su propio bloque. Si alguna crece demasiado, se puede mover tal cual
 * a admin/views/ sin tocar el resto.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notificaciones.php';

requerir_admin();

header('X-Robots-Tag: noindex, nofollow', true);

$view = $_GET['view'] ?? 'dashboard';

switch ($view) {
    case 'dashboard':     adm_dashboard();     break;
    case 'ordenes':       adm_ordenes();       break;
    case 'orden_editar':  adm_orden_editar();  break;
    case 'ticket':        adm_ticket();        break;
    case 'clientes':      adm_clientes();      break;
    case 'servicios':     adm_servicios();     break;
    case 'estados':       adm_estados();       break;
    case 'contenido':     adm_contenido();     break;
    case 'aliados':       adm_aliados();       break;
    case 'logout':        adm_logout();        break;
    default:
        http_response_code(404);
        adm_header('No encontrado');
        echo '<div class="vacio"><h3>Esa sección no existe</h3><p>Usa el menú de la izquierda.</p></div>';
        adm_footer();
}

// =====================================================================
// LAYOUT DEL PANEL
// =====================================================================

function adm_header(string $titulo): void
{
    $admin  = admin_actual();
    $actual = $_GET['view'] ?? 'dashboard';
    $marca  = contenido('sitio_nombre', 'CrazyPro-Reparaciones');

    $menu = [
        'Operación' => [
            'dashboard' => 'Pendientes',
            'ordenes'   => 'Órdenes',
            'clientes'  => 'Clientes',
            'aliados'   => 'Aliados',
        ],
        'Contenido del sitio' => [
            'servicios' => 'Servicios',
            'estados'   => 'Estados de orden',
            'contenido' => 'Textos e imágenes',
        ],
    ];
    ?>
<!DOCTYPE html>
<html lang="es" data-tema="oscuro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?> · Panel</title>
<link rel="icon" href="<?= url('assets/logo.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;700;800;900&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>?v=1">
<script>
(function () {
  try {
    var t = localStorage.getItem('cp_tema');
    if (t === 'claro' || t === 'oscuro') { document.documentElement.setAttribute('data-tema', t); }
  } catch (e) {}
})();
</script>
</head>
<body data-base="<?= e(CP_URL) ?>" data-csrf="<?= e(csrf_token()) ?>" data-logueado="0">
<div class="panel-layout">

  <aside class="panel-lateral no-imprimir">
    <a class="marca" href="<?= url('admin/admin.php?view=dashboard') ?>">
      <img src="<?= url('assets/logo.svg') ?>" alt="" width="32" height="32">
      <span class="marca__txt">Panel</span>
    </a>

    <?php foreach ($menu as $grupo => $enlaces): ?>
      <p class="separador"><?= e($grupo) ?></p>
      <?php foreach ($enlaces as $clave => $etiqueta): ?>
        <a href="<?= url('admin/admin.php?view=' . $clave) ?>"
           class="<?= ($actual === $clave || ($clave === 'ordenes' && in_array($actual, ['orden_editar', 'ticket'], true))) ? 'activo' : '' ?>">
          <?= e($etiqueta) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <p class="separador">Sesión</p>
    <a href="<?= url('index.php') ?>" target="_blank" rel="noopener">Ver el sitio ↗</a>
    <a href="<?= url('admin/admin.php?view=logout') ?>">Cerrar sesión</a>

    <p class="pequeno apagado" style="margin:1.4rem .8rem 0">
      <?= e($admin['nombre']) ?><br><?= e($marca) ?>
    </p>
  </aside>

  <div class="panel-cuerpo">
    <?php foreach (flash() as $f): ?>
      <div class="aviso aviso--<?= e($f['tipo']) ?> no-imprimir" style="width:auto;margin:0 0 1.2rem" role="status"><?= $f['mensaje'] ?></div>
    <?php endforeach; ?>
    <?php
}

function adm_footer(): void
{
    ?>
  </div>
</div>
<script src="<?= url('js/main.js') ?>?v=1" defer></script>
</body>
</html>
    <?php
}

/** Cabecera de página con título y acciones a la derecha. */
function adm_titulo(string $titulo, string $subtitulo = '', string $acciones = ''): void
{
    ?>
    <div class="panel-cabeza no-imprimir">
      <div>
        <h1><?= e($titulo) ?></h1>
        <?php if ($subtitulo !== ''): ?><p class="apagado" style="margin:0"><?= e($subtitulo) ?></p><?php endif; ?>
      </div>
      <?php if ($acciones !== ''): ?><div style="display:flex;gap:.6rem;flex-wrap:wrap"><?= $acciones ?></div><?php endif; ?>
    </div>
    <?php
}

function adm_logout(): void
{
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
    flash('info', 'Sesión de administrador cerrada.');
    redirigir('index.php');
}

/** Suma de lo que se le debe a un aliado (referidos no pagados). */
function adm_deuda_aliado(int $aliadoId): float
{
    $st = db()->prepare('SELECT COALESCE(SUM(comision), 0) FROM referidos WHERE aliado_id = ? AND pagado = 0');
    $st->execute([$aliadoId]);
    return (float) $st->fetchColumn();
}

// =====================================================================
// 1. DASHBOARD — pendientes, no estadísticas decorativas
// =====================================================================
function adm_dashboard(): void
{
    $pdo = db();

    $sinRevisar = $pdo->query(
        'SELECT o.id, o.folio, o.modelo, o.nombre_contacto, o.creado_en, e.nombre AS estado_nombre
         FROM ordenes o JOIN estados_orden e ON e.id = o.estado_id
         WHERE o.revisada = 0 ORDER BY o.creado_en ASC LIMIT 15'
    )->fetchAll();

    $vencidas = $pdo->query(
        'SELECT o.id, o.folio, o.modelo, o.fecha_promesa, e.nombre AS estado_nombre
         FROM ordenes o JOIN estados_orden e ON e.id = o.estado_id
         WHERE o.fecha_promesa IS NOT NULL AND e.es_final = 0
           AND o.fecha_promesa <= (CURDATE() + INTERVAL 2 DAY)
         ORDER BY o.fecha_promesa ASC LIMIT 15'
    )->fetchAll();

    $porPagar = $pdo->query(
        'SELECT a.id, a.nombre, a.codigo,
                COUNT(r.id) AS pendientes,
                COALESCE(SUM(r.comision), 0) AS deuda
         FROM aliados a
         JOIN referidos r ON r.aliado_id = a.id AND r.pagado = 0
         GROUP BY a.id, a.nombre, a.codigo
         HAVING deuda > 0
         ORDER BY deuda DESC'
    )->fetchAll();

    $enProceso = (int) $pdo->query(
        'SELECT COUNT(*) FROM ordenes o JOIN estados_orden e ON e.id = o.estado_id WHERE e.es_final = 0'
    )->fetchColumn();

    $listas = (int) $pdo->query(
        "SELECT COUNT(*) FROM ordenes o JOIN estados_orden e ON e.id = o.estado_id
         WHERE e.es_final = 0 AND e.nombre LIKE '%isto%'"
    )->fetchColumn();

    $delMes = (int) $pdo->query(
        'SELECT COUNT(*) FROM ordenes WHERE YEAR(creado_en) = YEAR(CURDATE()) AND MONTH(creado_en) = MONTH(CURDATE())'
    )->fetchColumn();

    $deudaTotal = 0.0;
    foreach ($porPagar as $p) { $deudaTotal += (float) $p['deuda']; }

    $topServicios = $pdo->query(
        'SELECT s.nombre, COUNT(o.id) AS total
         FROM ordenes o JOIN servicios s ON s.id = o.servicio_id
         GROUP BY s.id, s.nombre ORDER BY total DESC LIMIT 5'
    )->fetchAll();

    adm_header('Pendientes');
    adm_titulo(
        'Pendientes',
        'Lo que necesita tu atención hoy.',
        '<a class="btn btn--rojo btn--sm" href="' . url('admin/admin.php?view=ordenes&accion=nueva') . '">Nueva orden</a>'
    );
    ?>

    <div class="metricas">
      <div class="metrica <?= count($sinRevisar) > 0 ? 'metrica--alerta' : '' ?>">
        <strong><?= count($sinRevisar) ?></strong><span>Sin revisar</span>
      </div>
      <div class="metrica"><strong><?= $enProceso ?></strong><span>En el taller</span></div>
      <div class="metrica"><strong><?= $listas ?></strong><span>Listas para recoger</span></div>
      <div class="metrica"><strong><?= $delMes ?></strong><span>Órdenes este mes</span></div>
      <div class="metrica <?= $deudaTotal > 0 ? 'metrica--alerta' : '' ?>">
        <strong><?= e(number_format($deudaTotal, 0)) ?></strong><span>Pesos por pagar a aliados</span>
      </div>
    </div>

    <div class="dos-columnas">
      <div>
        <div class="tarjeta" style="margin-bottom:1.5rem">
          <h3>Órdenes nuevas sin revisar</h3>
          <?php if (!$sinRevisar): ?>
            <p class="apagado pequeno" style="margin:0">Nada pendiente. Todas las órdenes están revisadas.</p>
          <?php else: ?>
            <ul class="lista-pendientes">
              <?php foreach ($sinRevisar as $o): ?>
                <li>
                  <div>
                    <strong class="folio"><?= e($o['folio']) ?></strong>
                    <span class="pequeno apagado"> · <?= e($o['nombre_contacto'] ?: 'Sin nombre') ?> · <?= e($o['modelo'] ?: 'Equipo sin especificar') ?></span>
                    <br><span class="pequeno apagado"><?= e(fecha_fmt($o['creado_en'])) ?></span>
                  </div>
                  <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=orden_editar&id=' . (int) $o['id']) ?>">Revisar</a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="tarjeta">
          <h3>Plazos encima o vencidos</h3>
          <?php if (!$vencidas): ?>
            <p class="apagado pequeno" style="margin:0">Ninguna orden con fecha estimada próxima.</p>
          <?php else: ?>
            <ul class="lista-pendientes">
              <?php foreach ($vencidas as $o):
                $vencida = strtotime((string) $o['fecha_promesa']) < strtotime('today'); ?>
                <li>
                  <div>
                    <strong class="folio"><?= e($o['folio']) ?></strong>
                    <span class="pequeno apagado"> · <?= e($o['estado_nombre']) ?></span>
                    <br>
                    <span class="pequeno" style="color:<?= $vencida ? 'var(--accent-red)' : 'var(--text-muted)' ?>">
                      <?= $vencida ? 'Venció el ' : 'Para el ' ?><?= e(fecha_fmt($o['fecha_promesa'], false)) ?>
                    </span>
                  </div>
                  <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=orden_editar&id=' . (int) $o['id']) ?>">Abrir</a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="tarjeta" style="margin-bottom:1.5rem">
          <h3>Pagos pendientes a aliados</h3>
          <?php if (!$porPagar): ?>
            <p class="apagado pequeno" style="margin:0">Estás al corriente con todos los aliados.</p>
          <?php else: ?>
            <ul class="lista-pendientes">
              <?php foreach ($porPagar as $a): ?>
                <li>
                  <div>
                    <strong><?= e($a['nombre']) ?></strong>
                    <br><span class="pequeno apagado"><?= (int) $a['pendientes'] ?> referidos sin pagar</span>
                  </div>
                  <div style="text-align:right">
                    <strong style="color:var(--accent-red)"><?= e(precio_fmt($a['deuda'])) ?></strong>
                    <br><a class="pequeno" href="<?= url('admin/admin.php?view=aliados&id=' . (int) $a['id']) ?>">Ver detalle</a>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="tarjeta">
          <h3>Servicios más solicitados</h3>
          <?php if (!$topServicios): ?>
            <p class="apagado pequeno" style="margin:0">Todavía no hay órdenes con servicio asignado.</p>
          <?php else: ?>
            <ul class="lista-pendientes">
              <?php foreach ($topServicios as $s): ?>
                <li>
                  <span class="pequeno"><?= e($s['nombre']) ?></span>
                  <strong><?= (int) $s['total'] ?></strong>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php
    adm_footer();
}

// =====================================================================
// 2. ÓRDENES — listado, filtros y alta
// =====================================================================
function adm_ordenes(): void
{
    if (($_GET['accion'] ?? '') === 'nueva') {
        adm_orden_nueva();
        return;
    }

    $estadoFiltro = isset($_GET['estado']) && ctype_digit((string) $_GET['estado']) ? (int) $_GET['estado'] : 0;
    $busqueda     = limpiar($_GET['q'] ?? '', 60);

    $sql = 'SELECT o.*, e.nombre AS estado_nombre, e.color AS estado_color, e.es_final,
                   s.nombre AS servicio_nombre
            FROM ordenes o
            JOIN estados_orden e ON e.id = o.estado_id
            LEFT JOIN servicios s ON s.id = o.servicio_id
            WHERE 1 = 1';
    $params = [];

    if ($estadoFiltro > 0) {
        $sql .= ' AND o.estado_id = ?';
        $params[] = $estadoFiltro;
    }
    if ($busqueda !== '') {
        $sql .= ' AND (o.folio LIKE ? OR o.modelo LIKE ? OR o.nombre_contacto LIKE ?
                       OR o.tel_contacto LIKE ? OR o.email_contacto LIKE ?)';
        $like = '%' . $busqueda . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY o.creado_en DESC LIMIT 300';

    $st = db()->prepare($sql);
    $st->execute($params);
    $ordenes = $st->fetchAll();
    $estados = estados_lista(false);

    adm_header('Órdenes');
    adm_titulo(
        'Órdenes',
        count($ordenes) . ' resultados',
        '<a class="btn btn--rojo btn--sm" href="' . url('admin/admin.php?view=ordenes&accion=nueva') . '">Nueva orden</a>'
    );
    ?>

    <form method="get" action="<?= url('admin/admin.php') ?>" class="tarjeta" style="margin-bottom:1.5rem">
      <input type="hidden" name="view" value="ordenes">
      <div style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:2 1 240px">
          <label for="q">Buscar</label>
          <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Folio, equipo, cliente o teléfono">
        </div>
        <div style="flex:1 1 200px">
          <label for="estado">Estado</label>
          <select id="estado" name="estado">
            <option value="0">Todos</option>
            <?php foreach ($estados as $es): ?>
              <option value="<?= (int) $es['id'] ?>" <?= $estadoFiltro === (int) $es['id'] ? 'selected' : '' ?>>
                <?= e($es['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn--plata" type="submit">Filtrar</button>
        <?php if ($busqueda !== '' || $estadoFiltro > 0): ?>
          <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=ordenes') ?>">Limpiar</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if (!$ordenes): ?>
      <div class="vacio">
        <h3>No hay órdenes con esos criterios</h3>
        <p>Cambia el filtro o registra una orden nueva.</p>
        <a class="btn btn--rojo" href="<?= url('admin/admin.php?view=ordenes&accion=nueva') ?>">Nueva orden</a>
      </div>
    <?php else: ?>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead>
            <tr><th>Folio</th><th>Cliente</th><th>Equipo</th><th>Estado</th><th>Ingreso</th><th>Total</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($ordenes as $o):
              $total = total_orden($o, piezas_de_orden((int) $o['id'])); ?>
              <tr>
                <td class="folio">
                  <?= e($o['folio']) ?>
                  <?php if (!$o['revisada']): ?>
                    <span class="pastilla" style="color:var(--accent-red);margin-left:.4rem"><span class="pastilla__punto"></span>Nueva</span>
                  <?php endif; ?>
                </td>
                <td><?= e($o['nombre_contacto'] ?: '—') ?></td>
                <td><?= e($o['modelo'] ?: '—') ?></td>
                <td>
                  <span class="pastilla" style="color:<?= e($o['estado_color']) ?>">
                    <span class="pastilla__punto"></span><?= e($o['estado_nombre']) ?>
                  </span>
                </td>
                <td class="apagado"><?= e(fecha_fmt($o['creado_en'], false)) ?></td>
                <td><?= $total === null ? '<span class="apagado">Por definir</span>' : e(precio_fmt($total)) ?></td>
                <td>
                  <div class="acciones-fila">
                    <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=orden_editar&id=' . (int) $o['id']) ?>">Editar</a>
                    <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=ticket&id=' . (int) $o['id']) ?>" target="_blank">Ticket</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif;

    adm_footer();
}

// ---------------------------------------------------------------------
function adm_orden_nueva(): void
{
    $errores = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();

        $nombre    = limpiar($_POST['nombre_contacto'] ?? '', 120);
        $email     = limpiar($_POST['email_contacto'] ?? '', 190);
        $telefono  = solo_digitos($_POST['tel_contacto'] ?? '');
        $tipo      = (string) ($_POST['tipo_equipo'] ?? 'otro');
        $modelo    = limpiar($_POST['modelo'] ?? '', 160);
        $capacidad = limpiar($_POST['capacidad'] ?? '', 60);
        $imei      = limpiar($_POST['imei'] ?? '', 40);
        $dano      = limpiar($_POST['dano_reportado'] ?? '', 4000);
        $servicio  = ctype_digit((string) ($_POST['servicio_id'] ?? '')) ? (int) $_POST['servicio_id'] : null;
        $aliado    = ctype_digit((string) ($_POST['aliado_id'] ?? '')) ? (int) $_POST['aliado_id'] : null;
        $promesa   = limpiar($_POST['fecha_promesa'] ?? '', 10);
        $presup    = precio_desde_form($_POST['presupuesto'] ?? '');

        if ($nombre === '') { $errores[] = 'Falta el nombre del cliente.'; }
        if ($modelo === '') { $errores[] = 'Falta el modelo del equipo.'; }
        if (!in_array($tipo, ['pc','laptop','android','iphone','otro'], true)) { $tipo = 'otro'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errores[] = 'El correo no es válido.'; }

        if (!$errores) {
            $pdo = db();
            $descuento = 0.0;
            $comision  = 0.0;

            if ($aliado) {
                $st = $pdo->prepare('SELECT descuento_pct, comision FROM aliados WHERE id = ?');
                $st->execute([$aliado]);
                if ($a = $st->fetch()) {
                    $descuento = (float) $a['descuento_pct'];
                    $comision  = (float) $a['comision'];
                } else {
                    $aliado = null;
                }
            }

            $usuarioId = null;
            if ($email !== '') {
                $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
                $st->execute([$email]);
                $encontrado = $st->fetchColumn();
                $usuarioId = $encontrado ? (int) $encontrado : null;
            }

            $folio = '';
            try {
                $pdo->beginTransaction();

                $folio    = generar_folio();
                $estadoId = estado_inicial_id();

                $st = $pdo->prepare(
                    'INSERT INTO ordenes
                        (folio, usuario_id, servicio_id, estado_id, aliado_id, tipo_equipo, modelo,
                         capacidad, imei, dano_reportado, presupuesto, descuento_pct,
                         nombre_contacto, email_contacto, tel_contacto, fecha_promesa, origen, revisada)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "taller", 1)'
                );
                $st->execute([
                    $folio, $usuarioId, $servicio, $estadoId, $aliado, $tipo, $modelo,
                    $capacidad ?: null, $imei ?: null, $dano ?: null, $presup, $descuento,
                    $nombre, $email ?: null, $telefono ?: null, $promesa ?: null,
                ]);
                $ordenId = (int) $pdo->lastInsertId();

                $st = $pdo->prepare(
                    'INSERT INTO historial_estados (orden_id, estado_id, admin_id, comentario) VALUES (?, ?, ?, ?)'
                );
                $st->execute([$ordenId, $estadoId, $_SESSION['admin_id'], 'Equipo recibido en el taller.']);

                if ($aliado) {
                    $st = $pdo->prepare(
                        'INSERT INTO referidos (aliado_id, orden_id, origen, comision, nota)
                         VALUES (?, ?, "codigo", ?, ?)'
                    );
                    $st->execute([$aliado, $ordenId, $comision, 'Orden dada de alta en el taller']);
                }

                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                flash('error', 'No se pudo guardar la orden.' . (CP_DEBUG ? ' ' . e($ex->getMessage()) : ''));
                redirigir('admin/admin.php?view=ordenes&accion=nueva');
            }

            flash('exito', 'Orden ' . e($folio) . ' creada. <a href="' . url('admin/admin.php?view=ticket&id=' . $ordenId) . '" target="_blank">Imprimir ticket</a>');
            redirigir('admin/admin.php?view=orden_editar&id=' . $ordenId);
        }
    }

    $servicios = db()->query('SELECT id, nombre, categoria FROM servicios WHERE activo = 1 ORDER BY categoria, posicion')->fetchAll();
    $aliados   = db()->query('SELECT id, nombre, codigo FROM aliados WHERE activo = 1 ORDER BY nombre')->fetchAll();
    $cats      = categorias_servicios();

    adm_header('Nueva orden');
    adm_titulo('Nueva orden', 'Se genera el folio automáticamente al guardar.');
    ?>

    <?php if ($errores): ?>
      <div class="aviso aviso--error" style="width:auto;margin:0 0 1.2rem" role="alert">
        <?= e(implode(' ', $errores)) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="panel-form" style="max-width:820px">
      <?= csrf_campo() ?>

      <h3>Cliente</h3>
      <div class="campos-3">
        <div class="campo">
          <label for="nombre_contacto">Nombre</label>
          <input type="text" id="nombre_contacto" name="nombre_contacto" value="<?= e($_POST['nombre_contacto'] ?? '') ?>" required>
        </div>
        <div class="campo">
          <label for="tel_contacto">WhatsApp</label>
          <input type="tel" id="tel_contacto" name="tel_contacto" value="<?= e($_POST['tel_contacto'] ?? '') ?>" placeholder="7299244238">
        </div>
        <div class="campo">
          <label for="email_contacto">Correo</label>
          <input type="email" id="email_contacto" name="email_contacto" value="<?= e($_POST['email_contacto'] ?? '') ?>">
        </div>
      </div>

      <h3 style="margin-top:1.4rem">Equipo</h3>
      <div class="campos-3">
        <div class="campo">
          <label for="tipo_equipo">Tipo</label>
          <select id="tipo_equipo" name="tipo_equipo">
            <?php foreach (['pc' => 'PC de escritorio', 'laptop' => 'Laptop', 'android' => 'Android', 'iphone' => 'iPhone', 'otro' => 'Otro'] as $v => $t): ?>
              <option value="<?= e($v) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="modelo">Marca y modelo</label>
          <input type="text" id="modelo" name="modelo" value="<?= e($_POST['modelo'] ?? '') ?>" required>
        </div>
        <div class="campo">
          <label for="capacidad">Capacidad / RAM</label>
          <input type="text" id="capacidad" name="capacidad" value="<?= e($_POST['capacidad'] ?? '') ?>">
        </div>
      </div>

      <div class="campos-2">
        <div class="campo">
          <label for="imei">IMEI o número de serie</label>
          <input type="text" id="imei" name="imei" value="<?= e($_POST['imei'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="servicio_id">Servicio</label>
          <select id="servicio_id" name="servicio_id">
            <option value="">Por diagnosticar</option>
            <?php foreach ($cats as $clave => $etiqueta): ?>
              <optgroup label="<?= e($etiqueta) ?>">
                <?php foreach ($servicios as $s): if ($s['categoria'] !== $clave) { continue; } ?>
                  <option value="<?= (int) $s['id'] ?>"><?= e($s['nombre']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="campo">
        <label for="dano_reportado">Daño reportado por el cliente</label>
        <textarea id="dano_reportado" name="dano_reportado"><?= e($_POST['dano_reportado'] ?? '') ?></textarea>
      </div>

      <h3 style="margin-top:1.4rem">Presupuesto y plazo</h3>
      <div class="campos-3">
        <div class="campo">
          <label for="presupuesto">Presupuesto (MXN)</label>
          <input type="number" id="presupuesto" name="presupuesto" step="0.01" min="0" placeholder="Vacío = por definir">
          <p class="ayuda">Escribe 0 solo si es sin costo.</p>
        </div>
        <div class="campo">
          <label for="fecha_promesa">Fecha estimada de entrega</label>
          <input type="date" id="fecha_promesa" name="fecha_promesa">
        </div>
        <div class="campo">
          <label for="aliado_id">Aliado que lo refirió</label>
          <select id="aliado_id" name="aliado_id">
            <option value="">Ninguno</option>
            <?php foreach ($aliados as $a): ?>
              <option value="<?= (int) $a['id'] ?>"><?= e($a['nombre']) ?> (<?= e($a['codigo']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <p class="ayuda">Aplica su descuento y suma un referido.</p>
        </div>
      </div>

      <button class="btn btn--rojo" type="submit" style="margin-top:.6rem">Crear orden y generar folio</button>
      <a class="btn btn--plata" href="<?= url('admin/admin.php?view=ordenes') ?>">Cancelar</a>
    </form>

    <?php
    adm_footer();
}

// =====================================================================
// 3. EDICIÓN DE ORDEN — datos, estado (notifica), piezas
// =====================================================================
function adm_orden_editar(): void
{
    $id  = (int) ($_GET['id'] ?? 0);
    $pdo = db();

    $st = $pdo->prepare(
        'SELECT o.*, e.nombre AS estado_nombre, e.color AS estado_color, e.es_final,
                s.nombre AS servicio_nombre, a.nombre AS aliado_nombre
         FROM ordenes o
         JOIN estados_orden e ON e.id = o.estado_id
         LEFT JOIN servicios s ON s.id = o.servicio_id
         LEFT JOIN aliados a ON a.id = o.aliado_id
         WHERE o.id = ? LIMIT 1'
    );
    $st->execute([$id]);
    $orden = $st->fetch();

    if (!$orden) {
        http_response_code(404);
        flash('error', 'Esa orden no existe.');
        redirigir('admin/admin.php?view=ordenes');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'guardar') {
            $servicio = ctype_digit((string) ($_POST['servicio_id'] ?? '')) ? (int) $_POST['servicio_id'] : null;
            $tipo = (string) ($_POST['tipo_equipo'] ?? 'otro');
            if (!in_array($tipo, ['pc','laptop','android','iphone','otro'], true)) { $tipo = 'otro'; }

            $up = $pdo->prepare(
                'UPDATE ordenes SET modelo = ?, capacidad = ?, imei = ?, tipo_equipo = ?,
                        servicio_id = ?, dano_reportado = ?, diagnostico = ?, presupuesto = ?,
                        anticipo = ?, descuento_pct = ?, fecha_promesa = ?,
                        nombre_contacto = ?, email_contacto = ?, tel_contacto = ?, revisada = 1
                 WHERE id = ?'
            );
            $up->execute([
                limpiar($_POST['modelo'] ?? '', 160) ?: null,
                limpiar($_POST['capacidad'] ?? '', 60) ?: null,
                limpiar($_POST['imei'] ?? '', 40) ?: null,
                $tipo,
                $servicio,
                limpiar($_POST['dano_reportado'] ?? '', 4000) ?: null,
                limpiar($_POST['diagnostico'] ?? '', 4000) ?: null,
                precio_desde_form($_POST['presupuesto'] ?? ''),
                (float) (precio_desde_form($_POST['anticipo'] ?? '') ?? 0),
                (float) ($_POST['descuento_pct'] ?? 0),
                limpiar($_POST['fecha_promesa'] ?? '', 10) ?: null,
                limpiar($_POST['nombre_contacto'] ?? '', 120) ?: null,
                limpiar($_POST['email_contacto'] ?? '', 190) ?: null,
                solo_digitos($_POST['tel_contacto'] ?? '') ?: null,
                $id,
            ]);
            flash('exito', 'Cambios guardados.');
            redirigir('admin/admin.php?view=orden_editar&id=' . $id);
        }

        if ($accion === 'cambiar_estado') {
            $nuevoId    = (int) ($_POST['estado_id'] ?? 0);
            $comentario = limpiar($_POST['comentario'] ?? '', 2000);
            $notificar  = !empty($_POST['notificar']);

            $q = $pdo->prepare('SELECT * FROM estados_orden WHERE id = ? LIMIT 1');
            $q->execute([$nuevoId]);
            $estado = $q->fetch();

            if (!$estado) {
                flash('error', 'Ese estado no existe.');
                redirigir('admin/admin.php?view=orden_editar&id=' . $id);
            }

            $up = $pdo->prepare('UPDATE ordenes SET estado_id = ?, revisada = 1 WHERE id = ?');
            $up->execute([$nuevoId, $id]);

            $hi = $pdo->prepare(
                'INSERT INTO historial_estados (orden_id, estado_id, admin_id, comentario) VALUES (?, ?, ?, ?)'
            );
            $hi->execute([$id, $nuevoId, $_SESSION['admin_id'], $comentario ?: null]);

            if ($notificar) {
                $orden['estado_id'] = $nuevoId;
                $r = notificar_cambio_estado($orden, $estado, $comentario);

                if (!empty($r['link_manual'])) {
                    flash('info',
                        'Estado actualizado. La Cloud API no está configurada: '
                        . '<a href="' . e($r['link_manual']) . '" target="_blank" rel="noopener"><strong>abre WhatsApp con el mensaje listo</strong></a> '
                        . 'y solo presiona Enviar.');
                } elseif (!empty($r['whatsapp']['ok']) || !empty($r['correo']['ok'])) {
                    flash('exito', 'Estado actualizado y cliente notificado.');
                } else {
                    flash('info', 'Estado actualizado, pero no se pudo enviar el aviso. Revisa el correo o el WhatsApp del cliente.');
                }
            } else {
                flash('exito', 'Estado actualizado sin notificar al cliente.');
            }
            redirigir('admin/admin.php?view=orden_editar&id=' . $id);
        }

        if ($accion === 'pieza_agregar') {
            $nombrePieza = limpiar($_POST['pieza_nombre'] ?? '', 160);
            $tipoPieza   = (string) ($_POST['pieza_tipo'] ?? 'generico');
            if (!in_array($tipoPieza, ['generico','original','usado'], true)) { $tipoPieza = 'generico'; }

            if ($nombrePieza === '') {
                flash('error', 'Escribe el nombre de la pieza.');
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO piezas_orden (orden_id, nombre, tipo, cantidad, precio) VALUES (?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $id, $nombrePieza, $tipoPieza,
                    max(1, (int) ($_POST['pieza_cantidad'] ?? 1)),
                    precio_desde_form($_POST['pieza_precio'] ?? ''),
                ]);
                flash('exito', 'Pieza agregada.');
            }
            redirigir('admin/admin.php?view=orden_editar&id=' . $id);
        }

        if ($accion === 'pieza_borrar') {
            $del = $pdo->prepare('DELETE FROM piezas_orden WHERE id = ? AND orden_id = ?');
            $del->execute([(int) ($_POST['pieza_id'] ?? 0), $id]);
            flash('exito', 'Pieza eliminada.');
            redirigir('admin/admin.php?view=orden_editar&id=' . $id);
        }
    }

    $historial = historial_de_orden($id);
    $piezas    = piezas_de_orden($id);
    $total     = total_orden($orden, $piezas);
    $estados   = estados_lista();
    $servicios = $pdo->query('SELECT id, nombre, categoria FROM servicios ORDER BY categoria, posicion')->fetchAll();
    $cats      = categorias_servicios();

    $qn = $pdo->prepare('SELECT * FROM notificaciones_log WHERE orden_id = ? ORDER BY creado_en DESC LIMIT 10');
    $qn->execute([$id]);
    $notifs = $qn->fetchAll();

    adm_header('Orden ' . $orden['folio']);
    adm_titulo(
        'Folio ' . $orden['folio'],
        $orden['modelo'] ?: 'Equipo sin especificar',
        '<a class="btn btn--plata btn--sm" href="' . url('admin/admin.php?view=ticket&id=' . $id) . '" target="_blank">Ver ticket</a>'
        . '<a class="btn btn--plata btn--sm" href="' . url('admin/admin.php?view=ordenes') . '">Volver</a>'
    );
    ?>

    <div class="dos-columnas">
      <div>
        <form method="post" class="tarjeta" style="margin-bottom:1.5rem">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="guardar">
          <h3>Datos de la orden</h3>

          <div class="campos-3">
            <div class="campo">
              <label for="tipo_equipo">Tipo</label>
              <select id="tipo_equipo" name="tipo_equipo">
                <?php foreach (['pc' => 'PC', 'laptop' => 'Laptop', 'android' => 'Android', 'iphone' => 'iPhone', 'otro' => 'Otro'] as $v => $t): ?>
                  <option value="<?= e($v) ?>" <?= $orden['tipo_equipo'] === $v ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo">
              <label for="modelo">Modelo</label>
              <input type="text" id="modelo" name="modelo" value="<?= e((string) $orden['modelo']) ?>">
            </div>
            <div class="campo">
              <label for="capacidad">Capacidad / RAM</label>
              <input type="text" id="capacidad" name="capacidad" value="<?= e((string) $orden['capacidad']) ?>">
            </div>
          </div>

          <div class="campos-2">
            <div class="campo">
              <label for="imei">IMEI / Serie</label>
              <input type="text" id="imei" name="imei" value="<?= e((string) $orden['imei']) ?>">
            </div>
            <div class="campo">
              <label for="servicio_id">Servicio</label>
              <select id="servicio_id" name="servicio_id">
                <option value="">Por diagnosticar</option>
                <?php foreach ($cats as $clave => $etiqueta): ?>
                  <optgroup label="<?= e($etiqueta) ?>">
                    <?php foreach ($servicios as $s): if ($s['categoria'] !== $clave) { continue; } ?>
                      <option value="<?= (int) $s['id'] ?>" <?= (int) $orden['servicio_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= e($s['nombre']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="campo">
            <label for="dano_reportado">Daño reportado</label>
            <textarea id="dano_reportado" name="dano_reportado" style="min-height:80px"><?= e((string) $orden['dano_reportado']) ?></textarea>
          </div>

          <div class="campo">
            <label for="diagnostico">Diagnóstico del técnico (lo ve el cliente)</label>
            <textarea id="diagnostico" name="diagnostico"><?= e((string) $orden['diagnostico']) ?></textarea>
          </div>

          <div class="campos-3">
            <div class="campo">
              <label for="presupuesto">Presupuesto (MXN)</label>
              <input type="number" id="presupuesto" name="presupuesto" step="0.01" min="0"
                     value="<?= $orden['presupuesto'] === null ? '' : e((string) $orden['presupuesto']) ?>"
                     placeholder="Vacío = por definir">
            </div>
            <div class="campo">
              <label for="anticipo">Anticipo recibido</label>
              <input type="number" id="anticipo" name="anticipo" step="0.01" min="0" value="<?= e((string) $orden['anticipo']) ?>">
            </div>
            <div class="campo">
              <label for="descuento_pct">Descuento (%)</label>
              <input type="number" id="descuento_pct" name="descuento_pct" step="0.01" min="0" max="100" value="<?= e((string) $orden['descuento_pct']) ?>">
            </div>
          </div>

          <div class="campos-3">
            <div class="campo">
              <label for="nombre_contacto">Cliente</label>
              <input type="text" id="nombre_contacto" name="nombre_contacto" value="<?= e((string) $orden['nombre_contacto']) ?>">
            </div>
            <div class="campo">
              <label for="tel_contacto">WhatsApp</label>
              <input type="tel" id="tel_contacto" name="tel_contacto" value="<?= e((string) $orden['tel_contacto']) ?>">
            </div>
            <div class="campo">
              <label for="email_contacto">Correo</label>
              <input type="email" id="email_contacto" name="email_contacto" value="<?= e((string) $orden['email_contacto']) ?>">
            </div>
          </div>

          <div class="campo" style="max-width:260px">
            <label for="fecha_promesa">Fecha estimada de entrega</label>
            <input type="date" id="fecha_promesa" name="fecha_promesa" value="<?= e((string) $orden['fecha_promesa']) ?>">
          </div>

          <button class="btn btn--rojo" type="submit">Guardar cambios</button>
        </form>

        <div class="tarjeta" style="margin-bottom:1.5rem">
          <h3>Piezas usadas</h3>

          <?php if ($piezas): ?>
            <div class="tabla-envoltura" style="margin-bottom:1.2rem">
              <table class="tabla" style="min-width:auto">
                <thead><tr><th>Pieza</th><th>Tipo</th><th>Cant.</th><th>Precio</th><th></th></tr></thead>
                <tbody>
                  <?php foreach ($piezas as $p): ?>
                    <tr>
                      <td><?= e($p['nombre']) ?></td>
                      <td class="apagado"><?= e(ucfirst($p['tipo'])) ?></td>
                      <td><?= (int) $p['cantidad'] ?></td>
                      <td><?= e(precio_fmt($p['precio'])) ?></td>
                      <td>
                        <form method="post" style="margin:0">
                          <?= csrf_campo() ?>
                          <input type="hidden" name="accion" value="pieza_borrar">
                          <input type="hidden" name="pieza_id" value="<?= (int) $p['id'] ?>">
                          <button class="btn btn--plata btn--sm" type="submit" data-confirmar="¿Eliminar esta pieza?">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="apagado pequeno">Todavía no se registran piezas para esta orden.</p>
          <?php endif; ?>

          <form method="post">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="pieza_agregar">
            <div style="display:grid;grid-template-columns:2fr 1fr .6fr 1fr auto;gap:.6rem;align-items:flex-end">
              <div>
                <label for="pieza_nombre">Pieza</label>
                <input type="text" id="pieza_nombre" name="pieza_nombre" placeholder="Pantalla OLED iPhone 12" required>
              </div>
              <div>
                <label for="pieza_tipo">Tipo</label>
                <select id="pieza_tipo" name="pieza_tipo">
                  <option value="generico">Genérico</option>
                  <option value="original">Original</option>
                  <option value="usado">Usado</option>
                </select>
              </div>
              <div>
                <label for="pieza_cantidad">Cant.</label>
                <input type="number" id="pieza_cantidad" name="pieza_cantidad" value="1" min="1">
              </div>
              <div>
                <label for="pieza_precio">Precio</label>
                <input type="number" id="pieza_precio" name="pieza_precio" step="0.01" min="0" placeholder="Por definir">
              </div>
              <button class="btn btn--plata" type="submit">Agregar</button>
            </div>
          </form>
        </div>
      </div>

      <div>
        <form method="post" class="tarjeta" style="margin-bottom:1.5rem;border-color:var(--accent-red)">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="cambiar_estado">
          <h3>Estado</h3>

          <p style="margin:0 0 1rem">
            <span class="pastilla" style="color:<?= e($orden['estado_color']) ?>">
              <span class="pastilla__punto"></span><?= e($orden['estado_nombre']) ?>
            </span>
          </p>

          <div class="campo">
            <label for="estado_id">Cambiar a</label>
            <select id="estado_id" name="estado_id" required>
              <?php foreach ($estados as $es): ?>
                <option value="<?= (int) $es['id'] ?>" <?= (int) $orden['estado_id'] === (int) $es['id'] ? 'selected' : '' ?>>
                  <?= e($es['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="campo">
            <label for="comentario">Nota para el cliente (opcional)</label>
            <textarea id="comentario" name="comentario" style="min-height:76px" placeholder="Ya llegó la pantalla, mañana queda listo."></textarea>
          </div>

          <label style="display:flex;gap:.5rem;align-items:center;font-weight:500;color:var(--text-primary)">
            <input type="checkbox" name="notificar" value="1" checked style="width:auto">
            Avisar al cliente
          </label>

          <button class="btn btn--rojo btn--bloque" type="submit" style="margin-top:1rem">Actualizar estado</button>
        </form>

        <div class="tarjeta" style="margin-bottom:1.5rem">
          <h3>Resumen</h3>
          <dl style="margin:0">
            <div class="ticket__fila"><dt>Ingreso</dt><dd><?= e(fecha_fmt($orden['creado_en'], false)) ?></dd></div>
            <div class="ticket__fila"><dt>Origen</dt><dd><?= $orden['origen'] === 'web' ? 'Cotización web' : 'Taller' ?></dd></div>
            <?php if ($orden['aliado_nombre']): ?>
              <div class="ticket__fila"><dt>Aliado</dt><dd><?= e($orden['aliado_nombre']) ?></dd></div>
            <?php endif; ?>
            <?php if ((float) $orden['anticipo'] > 0): ?>
              <div class="ticket__fila"><dt>Anticipo</dt><dd><?= e(precio_fmt($orden['anticipo'])) ?></dd></div>
            <?php endif; ?>
          </dl>
          <div class="ticket__total">
            <span>Total</span>
            <strong><?= $total === null ? 'Por definir' : e(precio_fmt($total)) ?></strong>
          </div>
        </div>

        <div class="tarjeta" style="margin-bottom:1.5rem">
          <h3>Historial</h3>
          <ol class="linea-tiempo">
            <?php foreach (array_reverse($historial) as $i => $h): ?>
              <li class="<?= $i === 0 ? 'activo' : '' ?>">
                <strong><?= e($h['estado_nombre']) ?></strong>
                <span><?= e(fecha_fmt($h['creado_en'])) ?></span>
                <?php if ($h['comentario']): ?><p><?= nl2br(e($h['comentario'])) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>

        <div class="tarjeta">
          <h3>Avisos enviados</h3>
          <?php if (!$notifs): ?>
            <p class="apagado pequeno" style="margin:0">Todavía no se ha enviado ningún aviso.</p>
          <?php else: ?>
            <ul class="lista-pendientes">
              <?php foreach ($notifs as $n): ?>
                <li>
                  <div>
                    <strong class="pequeno"><?= e(ucfirst($n['canal'])) ?></strong>
                    <br><span class="pequeno apagado"><?= e(fecha_fmt($n['creado_en'])) ?></span>
                  </div>
                  <span class="pastilla pequeno" style="color:<?= $n['estado'] === 'enviado' ? '#21C07A' : ($n['estado'] === 'fallido' ? 'var(--accent-red)' : 'var(--accent-violet)') ?>">
                    <?= e($n['estado']) ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($orden['tel_contacto']): ?>
            <a class="btn btn--plata btn--sm btn--bloque" style="margin-top:.9rem" target="_blank" rel="noopener"
               href="<?= e(notif_link_manual($orden, 'Hola, te escribimos de ' . contenido('sitio_nombre', 'CrazyPro-Reparaciones') . ' sobre tu folio ' . $orden['folio'] . '.')) ?>">
              Escribirle por WhatsApp
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php
    adm_footer();
}

// =====================================================================
// 4. TICKET / ETIQUETA IMPRIMIBLE
// =====================================================================
function adm_ticket(): void
{
    $id = (int) ($_GET['id'] ?? 0);

    $st = db()->prepare(
        'SELECT o.*, e.nombre AS estado_nombre, s.nombre AS servicio_nombre
         FROM ordenes o
         JOIN estados_orden e ON e.id = o.estado_id
         LEFT JOIN servicios s ON s.id = o.servicio_id
         WHERE o.id = ? LIMIT 1'
    );
    $st->execute([$id]);
    $orden = $st->fetch();

    if (!$orden) {
        http_response_code(404);
        redirigir('admin/admin.php?view=ordenes');
    }

    $piezas = piezas_de_orden($id);
    $total  = total_orden($orden, $piezas);
    $marca  = contenido('sitio_nombre', 'CrazyPro-Reparaciones');
    ?>
<!DOCTYPE html>
<html lang="es" data-tema="claro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Ticket <?= e($orden['folio']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>?v=1">
</head>
<body style="padding:2rem 1rem">

<div class="no-imprimir" style="max-width:560px;margin:0 auto 1.2rem;display:flex;gap:.6rem;justify-content:space-between">
  <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=orden_editar&id=' . $id) ?>">← Volver a la orden</a>
  <button class="btn btn--rojo btn--sm" type="button" onclick="window.print()">Imprimir</button>
</div>

<div class="ticket">
  <div class="ticket__cabeza">
    <div>
      <img src="<?= url('assets/logo.svg') ?>" alt="" width="40" height="40">
      <p style="margin:.4rem 0 0;font-weight:700"><?= e($marca) ?></p>
      <p class="pequeno apagado" style="margin:0"><?= e(contenido('direccion', 'Huehuetoca, Estado de México')) ?></p>
      <p class="pequeno apagado" style="margin:0"><?= e(contenido('telefono_publico', '729-924-4238')) ?></p>
    </div>
    <div style="text-align:right">
      <p class="pequeno apagado" style="margin:0">Folio</p>
      <p class="ticket__folio" style="margin:0"><?= e($orden['folio']) ?></p>
      <p class="pequeno apagado" style="margin:.2rem 0 0"><?= e(fecha_fmt($orden['creado_en'], false)) ?></p>
    </div>
  </div>

  <dl style="margin:0">
    <div class="ticket__fila"><dt>Cliente</dt><dd><?= e($orden['nombre_contacto'] ?: '—') ?></dd></div>
    <?php if ($orden['tel_contacto']): ?>
      <div class="ticket__fila"><dt>Teléfono</dt><dd><?= e($orden['tel_contacto']) ?></dd></div>
    <?php endif; ?>
    <div class="ticket__fila"><dt>Equipo</dt><dd><?= e($orden['modelo'] ?: '—') ?></dd></div>
    <?php if ($orden['capacidad']): ?>
      <div class="ticket__fila"><dt>Capacidad</dt><dd><?= e($orden['capacidad']) ?></dd></div>
    <?php endif; ?>
    <?php if ($orden['imei']): ?>
      <div class="ticket__fila"><dt>IMEI / Serie</dt><dd><?= e($orden['imei']) ?></dd></div>
    <?php endif; ?>
    <div class="ticket__fila"><dt>Servicio</dt><dd><?= e($orden['servicio_nombre'] ?: 'Por diagnosticar') ?></dd></div>
    <div class="ticket__fila"><dt>Estado</dt><dd><?= e($orden['estado_nombre']) ?></dd></div>
    <?php if ($orden['fecha_promesa']): ?>
      <div class="ticket__fila"><dt>Entrega estimada</dt><dd><?= e(fecha_fmt($orden['fecha_promesa'], false)) ?></dd></div>
    <?php endif; ?>
  </dl>

  <?php if ($orden['dano_reportado']): ?>
    <p style="margin:1rem 0 .2rem;font-weight:700;font-size:.9rem">Daño reportado</p>
    <p style="font-size:.86rem;margin:0"><?= nl2br(e($orden['dano_reportado'])) ?></p>
  <?php endif; ?>

  <?php if ($orden['diagnostico']): ?>
    <p style="margin:1rem 0 .2rem;font-weight:700;font-size:.9rem">Diagnóstico</p>
    <p style="font-size:.86rem;margin:0"><?= nl2br(e($orden['diagnostico'])) ?></p>
  <?php endif; ?>

  <?php if ($piezas): ?>
    <p style="margin:1rem 0 .2rem;font-weight:700;font-size:.9rem">Piezas</p>
    <dl style="margin:0">
      <?php foreach ($piezas as $p): ?>
        <div class="ticket__fila">
          <dt><?= e($p['nombre']) ?> <span class="apagado">(<?= e($p['tipo']) ?>) ×<?= (int) $p['cantidad'] ?></span></dt>
          <dd><?= e(precio_fmt($p['precio'])) ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
  <?php endif; ?>

  <dl style="margin:0">
    <?php if ((float) $orden['anticipo'] > 0): ?>
      <div class="ticket__fila" style="margin-top:.6rem"><dt>Anticipo recibido</dt><dd><?= e(precio_fmt($orden['anticipo'])) ?></dd></div>
    <?php endif; ?>
    <?php if ((float) $orden['descuento_pct'] > 0): ?>
      <div class="ticket__fila"><dt>Descuento aplicado</dt><dd><?= e((string) (float) $orden['descuento_pct']) ?>%</dd></div>
    <?php endif; ?>
  </dl>

  <div class="ticket__total">
    <span>Total</span>
    <strong><?= $total === null ? 'Por definir' : e(precio_fmt($total)) ?></strong>
  </div>

  <?php if ($total !== null && (float) $orden['anticipo'] > 0): ?>
    <dl style="margin:0">
      <div class="ticket__fila"><dt>Resta por pagar</dt><dd><?= e(precio_fmt(max(0, $total - (float) $orden['anticipo']))) ?></dd></div>
    </dl>
  <?php endif; ?>

  <p class="ticket__pie">
    Consulta el avance en <?= e(str_replace(['https://', 'http://'], '', CP_URL)) ?> con tu folio <?= e($orden['folio']) ?>.<br>
    <?= e(contenido('politica_desbloqueo', '')) ?>
  </p>
</div>

</body>
</html>
    <?php
}

// =====================================================================
// 5. CLIENTES
// =====================================================================
function adm_clientes(): void
{
    $busqueda = limpiar($_GET['q'] ?? '', 60);

    $sql = 'SELECT u.*, COUNT(o.id) AS total_ordenes
            FROM usuarios u
            LEFT JOIN ordenes o ON o.usuario_id = u.id';
    $params = [];

    if ($busqueda !== '') {
        $sql .= ' WHERE u.nombre LIKE ? OR u.email LIKE ? OR u.whatsapp LIKE ?';
        $like = '%' . $busqueda . '%';
        array_push($params, $like, $like, $like);
    }
    $sql .= ' GROUP BY u.id ORDER BY u.creado_en DESC LIMIT 300';

    $st = db()->prepare($sql);
    $st->execute($params);
    $clientes = $st->fetchAll();

    adm_header('Clientes');
    adm_titulo('Clientes', count($clientes) . ' cuentas registradas');
    ?>

    <form method="get" class="tarjeta" style="margin-bottom:1.5rem">
      <input type="hidden" name="view" value="clientes">
      <div style="display:flex;gap:.7rem;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1 1 260px">
          <label for="q">Buscar</label>
          <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre, correo o WhatsApp">
        </div>
        <button class="btn btn--plata" type="submit">Buscar</button>
      </div>
    </form>

    <?php if (!$clientes): ?>
      <div class="vacio"><h3>Sin clientes registrados</h3><p>Aparecerán aquí cuando creen su cuenta desde el sitio.</p></div>
    <?php else: ?>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead><tr><th>Nombre</th><th>Correo</th><th>WhatsApp</th><th>Avisos</th><th>Órdenes</th><th>Alta</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($clientes as $c): ?>
              <tr>
                <td>
                  <?= e($c['nombre']) ?>
                  <?php if ($c['google_id']): ?><span class="pequeno apagado"> · Google</span><?php endif; ?>
                </td>
                <td class="apagado"><?= e($c['email']) ?></td>
                <td><?= e((string) $c['whatsapp'] ?: '—') ?></td>
                <td class="apagado"><?= e(ucfirst($c['canal_notif'])) ?></td>
                <td><?= (int) $c['total_ordenes'] ?></td>
                <td class="apagado"><?= e(fecha_fmt($c['creado_en'], false)) ?></td>
                <td>
                  <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=ordenes&q=' . rawurlencode($c['email'])) ?>">Sus órdenes</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif;

    adm_footer();
}

// =====================================================================
// 6. SERVICIOS (CMS)
// =====================================================================
function adm_servicios(): void
{
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();
        $accion = $_POST['accion'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);

        try {
            if ($accion === 'crear' || $accion === 'editar') {
                $categoria = (string) ($_POST['categoria'] ?? 'computo');
                if (!array_key_exists($categoria, categorias_servicios())) { $categoria = 'computo'; }

                $nombre = limpiar($_POST['nombre'] ?? '', 160);
                if ($nombre === '') {
                    throw new RuntimeException('El servicio necesita un nombre.');
                }

                $imagen = subir_imagen('imagen', 'servicio');

                if ($accion === 'crear') {
                    $st = $pdo->prepare(
                        'INSERT INTO servicios (categoria, nombre, descripcion, imagen, precio, posicion, activo)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $st->execute([
                        $categoria, $nombre,
                        limpiar($_POST['descripcion'] ?? '', 1000) ?: null,
                        $imagen,
                        precio_desde_form($_POST['precio'] ?? ''),
                        (int) ($_POST['posicion'] ?? 0),
                        empty($_POST['activo']) ? 0 : 1,
                    ]);
                    flash('exito', 'Servicio agregado al catálogo.');
                } else {
                    $sql = 'UPDATE servicios SET categoria = ?, nombre = ?, descripcion = ?, precio = ?, posicion = ?, activo = ?';
                    $params = [
                        $categoria, $nombre,
                        limpiar($_POST['descripcion'] ?? '', 1000) ?: null,
                        precio_desde_form($_POST['precio'] ?? ''),
                        (int) ($_POST['posicion'] ?? 0),
                        empty($_POST['activo']) ? 0 : 1,
                    ];
                    if ($imagen !== null) {
                        $sql .= ', imagen = ?';
                        $params[] = $imagen;
                    }
                    $sql .= ' WHERE id = ?';
                    $params[] = $id;

                    $pdo->prepare($sql)->execute($params);
                    flash('exito', 'Servicio actualizado.');
                }
            } elseif ($accion === 'alternar') {
                $pdo->prepare('UPDATE servicios SET activo = 1 - activo WHERE id = ?')->execute([$id]);
                flash('exito', 'Cambió la disponibilidad del servicio.');
            } elseif ($accion === 'borrar') {
                $pdo->prepare('DELETE FROM servicios WHERE id = ?')->execute([$id]);
                flash('exito', 'Servicio eliminado. Las órdenes que lo usaban conservan su historial.');
            }
        } catch (Throwable $ex) {
            flash('error', e($ex->getMessage()));
        }
        redirigir('admin/admin.php?view=servicios');
    }

    $editarId = (int) ($_GET['editar'] ?? 0);
    $editando = null;
    if ($editarId > 0) {
        $st = $pdo->prepare('SELECT * FROM servicios WHERE id = ?');
        $st->execute([$editarId]);
        $editando = $st->fetch() ?: null;
    }

    $servicios = $pdo->query('SELECT * FROM servicios ORDER BY categoria, posicion, id')->fetchAll();
    $cats = categorias_servicios();

    adm_header('Servicios');
    adm_titulo('Catálogo de servicios', 'Precio vacío = "Precio por definir". Precio 0 = sin costo.');
    ?>

    <div class="dos-columnas">
      <div>
        <?php foreach ($cats as $clave => $etiqueta):
          $delGrupo = [];
          foreach ($servicios as $s) {
              if ($s['categoria'] === $clave) { $delGrupo[] = $s; }
          }
          if (!$delGrupo) { continue; } ?>
          <h3 style="margin-top:1.4rem"><?= e($etiqueta) ?></h3>
          <div class="tabla-envoltura" style="margin-bottom:1rem">
            <table class="tabla">
              <thead><tr><th>Servicio</th><th>Precio</th><th>Orden</th><th>Visible</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($delGrupo as $s): ?>
                  <tr>
                    <td>
                      <strong><?= e($s['nombre']) ?></strong>
                      <?php if ($s['descripcion']): ?>
                        <br><span class="pequeno apagado"><?= e(mb_substr($s['descripcion'], 0, 80)) ?><?= mb_strlen($s['descripcion']) > 80 ? '…' : '' ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?= $s['precio'] === null ? '<span class="apagado">Por definir</span>' : e(precio_fmt($s['precio'])) ?></td>
                    <td class="apagado"><?= (int) $s['posicion'] ?></td>
                    <td>
                      <form method="post" style="margin:0">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="alternar">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <button class="pastilla" type="submit" style="cursor:pointer;background:none;color:<?= $s['activo'] ? '#21C07A' : 'var(--text-muted)' ?>">
                          <span class="pastilla__punto"></span><?= $s['activo'] ? 'Activo' : 'Pausado' ?>
                        </button>
                      </form>
                    </td>
                    <td>
                      <div class="acciones-fila">
                        <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=servicios&editar=' . (int) $s['id']) ?>">Editar</a>
                        <form method="post" style="margin:0">
                          <?= csrf_campo() ?>
                          <input type="hidden" name="accion" value="borrar">
                          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                          <button class="btn btn--plata btn--sm" type="submit" data-confirmar="¿Eliminar «<?= e($s['nombre']) ?>» del catálogo?">Borrar</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      </div>

      <div>
        <form method="post" enctype="multipart/form-data" class="tarjeta" style="position:sticky;top:1.5rem">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="<?= $editando ? 'editar' : 'crear' ?>">
          <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
          <?php endif; ?>

          <h3><?= $editando ? 'Editar servicio' : 'Nuevo servicio' ?></h3>

          <div class="campo">
            <label for="s_categoria">Categoría</label>
            <select id="s_categoria" name="categoria">
              <?php foreach ($cats as $clave => $etiqueta): ?>
                <option value="<?= e($clave) ?>" <?= ($editando && $editando['categoria'] === $clave) ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="campo">
            <label for="s_nombre">Nombre</label>
            <input type="text" id="s_nombre" name="nombre" value="<?= e($editando['nombre'] ?? '') ?>" required>
          </div>

          <div class="campo">
            <label for="s_desc">Descripción corta</label>
            <textarea id="s_desc" name="descripcion" style="min-height:86px"><?= e($editando['descripcion'] ?? '') ?></textarea>
          </div>

          <div class="campos-2">
            <div class="campo">
              <label for="s_precio">Precio (MXN)</label>
              <input type="number" id="s_precio" name="precio" step="0.01" min="0"
                     value="<?= ($editando && $editando['precio'] !== null) ? e((string) $editando['precio']) : '' ?>"
                     placeholder="Vacío = por definir">
            </div>
            <div class="campo">
              <label for="s_pos">Orden</label>
              <input type="number" id="s_pos" name="posicion" value="<?= e((string) ($editando['posicion'] ?? 0)) ?>">
            </div>
          </div>

          <div class="campo">
            <label for="s_img">Imagen</label>
            <input type="file" id="s_img" name="imagen" accept="image/*">
            <?php if (!empty($editando['imagen'])): ?>
              <img src="<?= e(img_url($editando['imagen'])) ?>" alt="" style="margin-top:.6rem;border-radius:6px;max-height:110px">
              <p class="ayuda">Sube otra solo si quieres reemplazarla.</p>
            <?php endif; ?>
          </div>

          <label style="display:flex;gap:.5rem;align-items:center;color:var(--text-primary);font-weight:500">
            <input type="checkbox" name="activo" value="1" style="width:auto" <?= (!$editando || $editando['activo']) ? 'checked' : '' ?>>
            Visible en el sitio
          </label>

          <button class="btn btn--rojo btn--bloque" type="submit" style="margin-top:1rem">
            <?= $editando ? 'Guardar cambios' : 'Agregar al catálogo' ?>
          </button>
          <?php if ($editando): ?>
            <a class="btn btn--plata btn--bloque" style="margin-top:.5rem" href="<?= url('admin/admin.php?view=servicios') ?>">Cancelar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php
    adm_footer();
}

// =====================================================================
// 7. ESTADOS DE ORDEN (CMS)
// =====================================================================
function adm_estados(): void
{
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();
        $accion = $_POST['accion'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);

        if ($accion === 'crear') {
            $nombre = limpiar($_POST['nombre'] ?? '', 60);
            if ($nombre === '') {
                flash('error', 'El estado necesita un nombre.');
            } else {
                $st = $pdo->prepare(
                    'INSERT INTO estados_orden (nombre, posicion, color, activo) VALUES (?, ?, ?, 1)'
                );
                $st->execute([
                    $nombre,
                    (int) ($_POST['posicion'] ?? 0),
                    limpiar($_POST['color'] ?? '#8B5CF6', 9),
                ]);
                flash('exito', 'Estado creado. Ya aparece al cambiar el estado de una orden.');
            }
        } elseif ($accion === 'guardar') {
            $st = $pdo->prepare('UPDATE estados_orden SET nombre = ?, posicion = ?, color = ?, activo = ? WHERE id = ?');
            $st->execute([
                limpiar($_POST['nombre'] ?? '', 60),
                (int) ($_POST['posicion'] ?? 0),
                limpiar($_POST['color'] ?? '#8B5CF6', 9),
                empty($_POST['activo']) ? 0 : 1,
                $id,
            ]);
            flash('exito', 'Estado actualizado.');
        } elseif ($accion === 'borrar') {
            $q = $pdo->prepare('SELECT protegido, nombre FROM estados_orden WHERE id = ?');
            $q->execute([$id]);
            $es = $q->fetch();

            if (!$es) {
                flash('error', 'Ese estado ya no existe.');
            } elseif ((int) $es['protegido'] === 1) {
                flash('error', 'El estado «' . e($es['nombre']) . '» está protegido: es el inicial o el final del flujo.');
            } else {
                $uso = $pdo->prepare('SELECT COUNT(*) FROM ordenes WHERE estado_id = ?');
                $uso->execute([$id]);
                if ((int) $uso->fetchColumn() > 0) {
                    flash('error', 'No se puede borrar: hay órdenes en ese estado. Pásalas a otro estado primero, o desactívalo.');
                } else {
                    $pdo->prepare('DELETE FROM historial_estados WHERE estado_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM estados_orden WHERE id = ?')->execute([$id]);
                    flash('exito', 'Estado eliminado.');
                }
            }
        }
        redirigir('admin/admin.php?view=estados');
    }

    $estados = $pdo->query(
        'SELECT e.*, (SELECT COUNT(*) FROM ordenes o WHERE o.estado_id = e.id) AS en_uso
         FROM estados_orden e ORDER BY e.posicion, e.id'
    )->fetchAll();

    adm_header('Estados de orden');
    adm_titulo('Estados de orden', 'El número de posición define el flujo. Los estados inicial y final no se pueden borrar.');
    ?>

    <div class="dos-columnas">
      <div>
        <?php foreach ($estados as $es): ?>
          <form method="post" class="tarjeta" style="margin-bottom:.9rem">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $es['id'] ?>">

            <div style="display:grid;grid-template-columns:2fr .7fr .8fr auto auto;gap:.7rem;align-items:flex-end">
              <div>
                <label for="es_n_<?= (int) $es['id'] ?>">Nombre</label>
                <input type="text" id="es_n_<?= (int) $es['id'] ?>" name="nombre" value="<?= e($es['nombre']) ?>" required>
              </div>
              <div>
                <label for="es_p_<?= (int) $es['id'] ?>">Posición</label>
                <input type="number" id="es_p_<?= (int) $es['id'] ?>" name="posicion" value="<?= (int) $es['posicion'] ?>">
              </div>
              <div>
                <label for="es_c_<?= (int) $es['id'] ?>">Color</label>
                <input type="text" id="es_c_<?= (int) $es['id'] ?>" name="color" value="<?= e($es['color']) ?>" style="font-family:monospace">
              </div>
              <label style="display:flex;gap:.4rem;align-items:center;color:var(--text-primary);font-weight:500;padding-bottom:.7rem">
                <input type="checkbox" name="activo" value="1" style="width:auto" <?= $es['activo'] ? 'checked' : '' ?>>
                Activo
              </label>
              <button class="btn btn--plata btn--sm" type="submit">Guardar</button>
            </div>

            <div style="display:flex;gap:.7rem;align-items:center;margin-top:.8rem;flex-wrap:wrap">
              <span class="pastilla" style="color:<?= e($es['color']) ?>"><span class="pastilla__punto"></span><?= e($es['nombre']) ?></span>
              <span class="pequeno apagado"><?= (int) $es['en_uso'] ?> órdenes en este estado</span>
              <?php if ((int) $es['es_inicial'] === 1): ?><span class="pequeno apagado">· estado inicial</span><?php endif; ?>
              <?php if ((int) $es['es_final'] === 1): ?><span class="pequeno apagado">· estado final</span><?php endif; ?>
              <?php if ((int) $es['protegido'] === 1): ?><span class="pequeno apagado">· protegido</span><?php endif; ?>
            </div>
          </form>

          <?php if ((int) $es['protegido'] === 0): ?>
            <form method="post" style="margin:-.5rem 0 1.2rem;text-align:right">
              <?= csrf_campo() ?>
              <input type="hidden" name="accion" value="borrar">
              <input type="hidden" name="id" value="<?= (int) $es['id'] ?>">
              <button class="btn btn--plata btn--sm" type="submit" data-confirmar="¿Eliminar el estado «<?= e($es['nombre']) ?>»?">Eliminar estado</button>
            </form>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <div>
        <form method="post" class="tarjeta" style="position:sticky;top:1.5rem">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="crear">
          <h3>Nuevo estado</h3>

          <div class="campo">
            <label for="n_nombre">Nombre</label>
            <input type="text" id="n_nombre" name="nombre" placeholder="Esperando aprobación del cliente" required>
          </div>
          <div class="campos-2">
            <div class="campo">
              <label for="n_pos">Posición</label>
              <input type="number" id="n_pos" name="posicion" value="45">
              <p class="ayuda">Entre 30 y 50 queda a media ruta.</p>
            </div>
            <div class="campo">
              <label for="n_color">Color</label>
              <input type="text" id="n_color" name="color" value="#8B5CF6" style="font-family:monospace">
            </div>
          </div>

          <button class="btn btn--rojo btn--bloque" type="submit">Crear estado</button>
        </form>
      </div>
    </div>

    <?php
    adm_footer();
}

// =====================================================================
// 8. CONTENIDO DEL SITIO (CMS)
// =====================================================================
function adm_contenido(): void
{
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();

        if (($_POST['accion'] ?? '') === 'rotar_slug') {
            $nuevo   = 'acceso-' . bin2hex(random_bytes(3)) . '.php';
            $actual  = admin_slug();
            $origen  = __DIR__ . DIRECTORY_SEPARATOR . $actual;
            $destino = __DIR__ . DIRECTORY_SEPARATOR . $nuevo;

            if (!is_file($origen)) {
                flash('error', 'No se encontró el archivo <code>' . e($actual) . '</code> en /admin. Renómbralo a mano y actualiza la clave <code>admin_slug</code>.');
            } elseif (@rename($origen, $destino)) {
                contenido_guardar('admin_slug', $nuevo, 'Archivo de acceso al panel', 'sistema');
                flash('exito', 'Nueva dirección de acceso: <code>' . e(url('admin/' . $nuevo)) . '</code> — guárdala ahora, la anterior ya no funciona.');
            } else {
                flash('error', 'Windows no permitió renombrar el archivo (puede estar abierto en un editor). Ciérralo e inténtalo otra vez.');
            }
            redirigir('admin/admin.php?view=contenido');
        }

        $claves = $_POST['campo'] ?? [];
        if (is_array($claves)) {
            $up = $pdo->prepare('UPDATE contenido SET valor = ? WHERE clave = ? AND editable = 1');
            foreach ($claves as $clave => $valor) {
                $up->execute([limpiar((string) $valor, 5000), (string) $clave]);
            }
        }

        $imagenes = $pdo->query("SELECT clave FROM contenido WHERE tipo = 'imagen' AND editable = 1")->fetchAll();
        foreach ($imagenes as $img) {
            try {
                $ruta = subir_imagen('img_' . $img['clave'], $img['clave']);
                if ($ruta !== null) {
                    $up = $pdo->prepare('UPDATE contenido SET valor = ? WHERE clave = ?');
                    $up->execute([$ruta, $img['clave']]);
                }
            } catch (Throwable $ex) {
                flash('error', e($ex->getMessage()));
            }
        }

        flash('exito', 'Contenido del sitio actualizado.');
        redirigir('admin/admin.php?view=contenido');
    }

    $filas = $pdo->query('SELECT * FROM contenido WHERE editable = 1 ORDER BY grupo, posicion, clave')->fetchAll();

    $grupos = [];
    foreach ($filas as $f) {
        $grupos[$f['grupo']][] = $f;
    }

    $titulosGrupo = [
        'general'  => 'Identidad del sitio',
        'portada'  => 'Textos de la portada',
        'contacto' => 'Contacto y WhatsApp',
    ];

    adm_header('Textos e imágenes');
    adm_titulo('Textos e imágenes', 'Todo lo que se ve en el sitio público, sin tocar código.');
    ?>

    <form method="post" enctype="multipart/form-data" style="max-width:820px">
      <?= csrf_campo() ?>

      <?php foreach ($grupos as $grupo => $campos): ?>
        <div class="tarjeta" style="margin-bottom:1.3rem">
          <h3><?= e($titulosGrupo[$grupo] ?? ucfirst($grupo)) ?></h3>

          <?php foreach ($campos as $c): ?>
            <div class="campo">
              <label for="c_<?= e($c['clave']) ?>"><?= e($c['etiqueta']) ?></label>

              <?php if ($c['tipo'] === 'textarea'): ?>
                <textarea id="c_<?= e($c['clave']) ?>" name="campo[<?= e($c['clave']) ?>]" style="min-height:92px"><?= e((string) $c['valor']) ?></textarea>

              <?php elseif ($c['tipo'] === 'imagen'): ?>
                <?php if (!empty($c['valor'])): ?>
                  <img src="<?= e(img_url($c['valor'])) ?>" alt="" style="max-height:80px;margin-bottom:.5rem;border-radius:6px">
                <?php endif; ?>
                <input type="file" id="c_<?= e($c['clave']) ?>" name="img_<?= e($c['clave']) ?>" accept="image/*">
                <p class="ayuda">JPG, PNG, GIF o WebP, hasta 4 MB.</p>

              <?php else: ?>
                <input type="text" id="c_<?= e($c['clave']) ?>" name="campo[<?= e($c['clave']) ?>]" value="<?= e((string) $c['valor']) ?>">
              <?php endif; ?>

              <?php if ($c['clave'] === 'whatsapp_numero'): ?>
                <p class="ayuda">Con lada de país y sin signos: para México, <code>521</code> + los 10 dígitos.</p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <button class="btn btn--rojo" type="submit">Guardar todo</button>
    </form>

    <div class="tarjeta" style="max-width:820px;margin-top:2rem;border-color:var(--accent-red)">
      <h3>Dirección de acceso al panel</h3>
      <p class="pequeno">Actual: <code><?= e(url('admin/' . admin_slug())) ?></code></p>
      <p class="pequeno apagado">
        Si sospechas que alguien más conoce esta dirección, rótala: el archivo se renombra solo y la dirección anterior deja de existir.
        Anota la nueva antes de cerrar sesión.
      </p>
      <form method="post" style="margin:0">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="rotar_slug">
        <button class="btn btn--plata btn--sm" type="submit"
                data-confirmar="La dirección actual dejará de funcionar de inmediato. ¿Continuar?">
          Generar nueva dirección
        </button>
      </form>
    </div>

    <?php
    adm_footer();
}

// =====================================================================
// 9. ALIADOS Y REFERIDOS
// =====================================================================
function adm_aliados(): void
{
    $pdo = db();

    // "+1" por fetch, sin recargar la página.
    if (($_GET['accion'] ?? '') === 'sumar_ajax') {
        header('Content-Type: application/json; charset=utf-8');
        csrf_verificar();

        $aliadoId = (int) ($_POST['aliado_id'] ?? 0);
        $q = $pdo->prepare('SELECT comision FROM aliados WHERE id = ? AND activo = 1');
        $q->execute([$aliadoId]);
        $aliado = $q->fetch();

        if (!$aliado) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Aliado no encontrado']);
            exit;
        }

        $ins = $pdo->prepare(
            'INSERT INTO referidos (aliado_id, origen, comision, nota) VALUES (?, "manual", ?, ?)'
        );
        $ins->execute([$aliadoId, (float) $aliado['comision'], 'Registrado a mano desde el panel']);

        $tot = $pdo->prepare('SELECT COUNT(*) FROM referidos WHERE aliado_id = ?');
        $tot->execute([$aliadoId]);

        echo json_encode([
            'ok'    => true,
            'total' => (int) $tot->fetchColumn(),
            'deuda' => precio_fmt(adm_deuda_aliado($aliadoId)),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verificar();
        $accion = $_POST['accion'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);

        if ($accion === 'crear' || $accion === 'editar') {
            $nombre = limpiar($_POST['nombre'] ?? '', 160);
            $codigo = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['codigo'] ?? '')));

            if ($nombre === '' || $codigo === '') {
                flash('error', 'El aliado necesita nombre y código.');
            } else {
                try {
                    if ($accion === 'crear') {
                        $st = $pdo->prepare(
                            'INSERT INTO aliados (nombre, codigo, contacto, telefono, descuento_pct, comision)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $st->execute([
                            $nombre, $codigo,
                            limpiar($_POST['contacto'] ?? '', 160) ?: null,
                            solo_digitos($_POST['telefono'] ?? '') ?: null,
                            (float) ($_POST['descuento_pct'] ?? 0),
                            (float) ($_POST['comision'] ?? 0),
                        ]);
                        flash('exito', 'Aliado registrado con el código ' . e($codigo) . '.');
                    } else {
                        $st = $pdo->prepare(
                            'UPDATE aliados SET nombre = ?, codigo = ?, contacto = ?, telefono = ?,
                                    descuento_pct = ?, comision = ?, activo = ? WHERE id = ?'
                        );
                        $st->execute([
                            $nombre, $codigo,
                            limpiar($_POST['contacto'] ?? '', 160) ?: null,
                            solo_digitos($_POST['telefono'] ?? '') ?: null,
                            (float) ($_POST['descuento_pct'] ?? 0),
                            (float) ($_POST['comision'] ?? 0),
                            empty($_POST['activo']) ? 0 : 1,
                            $id,
                        ]);
                        flash('exito', 'Aliado actualizado.');
                    }
                } catch (PDOException $ex) {
                    flash('error', 'Ese código ya lo usa otro aliado. Elige uno distinto.');
                }
            }
        } elseif ($accion === 'sumar') {
            $q = $pdo->prepare('SELECT comision FROM aliados WHERE id = ?');
            $q->execute([$id]);
            if ($a = $q->fetch()) {
                $ins = $pdo->prepare('INSERT INTO referidos (aliado_id, origen, comision, nota) VALUES (?, "manual", ?, ?)');
                $ins->execute([$id, (float) $a['comision'], 'Registrado a mano desde el panel']);
                flash('exito', 'Referido sumado.');
            }
        } elseif ($accion === 'pagar') {
            $pdo->prepare('UPDATE referidos SET pagado = 1, pagado_en = NOW() WHERE id = ?')
                ->execute([(int) ($_POST['referido_id'] ?? 0)]);
            flash('exito', 'Referido marcado como pagado.');
        } elseif ($accion === 'despagar') {
            $pdo->prepare('UPDATE referidos SET pagado = 0, pagado_en = NULL WHERE id = ?')
                ->execute([(int) ($_POST['referido_id'] ?? 0)]);
            flash('info', 'Referido marcado como no pagado.');
        } elseif ($accion === 'pagar_todo') {
            $pdo->prepare('UPDATE referidos SET pagado = 1, pagado_en = NOW() WHERE aliado_id = ? AND pagado = 0')
                ->execute([$id]);
            flash('exito', 'Cuenta saldada con ese aliado.');
        } elseif ($accion === 'borrar_referido') {
            $pdo->prepare('DELETE FROM referidos WHERE id = ?')->execute([(int) ($_POST['referido_id'] ?? 0)]);
            flash('exito', 'Referido eliminado.');
        }

        $volverA = (int) ($_POST['volver_a'] ?? 0);
        redirigir('admin/admin.php?view=aliados' . ($volverA > 0 ? '&id=' . $volverA : ''));
    }

    $detalleId = (int) ($_GET['id'] ?? 0);
    if ($detalleId > 0) {
        adm_aliado_detalle($detalleId);
        return;
    }

    $aliados = $pdo->query(
        'SELECT a.*,
                (SELECT COUNT(*) FROM referidos r WHERE r.aliado_id = a.id) AS total_referidos,
                (SELECT COALESCE(SUM(r.comision), 0) FROM referidos r WHERE r.aliado_id = a.id AND r.pagado = 0) AS deuda
         FROM aliados a ORDER BY a.activo DESC, a.nombre'
    )->fetchAll();

    $editarId = (int) ($_GET['editar'] ?? 0);
    $editando = null;
    if ($editarId > 0) {
        $st = $pdo->prepare('SELECT * FROM aliados WHERE id = ?');
        $st->execute([$editarId]);
        $editando = $st->fetch() ?: null;
    }

    adm_header('Aliados');
    adm_titulo('Aliados y referidos', 'El botón +1 sirve para las recomendaciones de boca en boca, sin código de por medio.');
    ?>

    <div class="dos-columnas">
      <div>
        <?php if (!$aliados): ?>
          <div class="vacio"><h3>Todavía no hay aliados</h3><p>Registra el primero con el formulario de la derecha.</p></div>
        <?php else: ?>
          <div class="tabla-envoltura">
            <table class="tabla">
              <thead><tr><th>Aliado</th><th>Código</th><th>Desc.</th><th>Comisión</th><th>Referidos</th><th>Se le debe</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($aliados as $a): ?>
                  <tr>
                    <td>
                      <strong><?= e($a['nombre']) ?></strong>
                      <?php if (!$a['activo']): ?><span class="pequeno apagado"> · inactivo</span><?php endif; ?>
                      <?php if ($a['contacto']): ?><br><span class="pequeno apagado"><?= e($a['contacto']) ?></span><?php endif; ?>
                    </td>
                    <td class="folio"><?= e($a['codigo']) ?></td>
                    <td><?= e((string) (float) $a['descuento_pct']) ?>%</td>
                    <td><?= e(precio_fmt($a['comision'])) ?></td>
                    <td><strong data-total-referidos="<?= (int) $a['id'] ?>"><?= (int) $a['total_referidos'] ?></strong></td>
                    <td>
                      <strong data-deuda="<?= (int) $a['id'] ?>" style="color:<?= (float) $a['deuda'] > 0 ? 'var(--accent-red)' : 'inherit' ?>">
                        <?= e(precio_fmt($a['deuda'])) ?>
                      </strong>
                    </td>
                    <td>
                      <div class="acciones-fila">
                        <button class="btn btn--rojo btn--sm" type="button" data-accion="sumar-referido" data-aliado="<?= (int) $a['id'] ?>">+1</button>
                        <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=aliados&id=' . (int) $a['id']) ?>">Detalle</a>
                        <a class="btn btn--plata btn--sm" href="<?= url('admin/admin.php?view=aliados&editar=' . (int) $a['id']) ?>">Editar</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="ayuda">El botón +1 suma un referido con la comisión configurada y actualiza el total sin recargar la página.</p>
        <?php endif; ?>
      </div>

      <div>
        <form method="post" class="tarjeta" style="position:sticky;top:1.5rem">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="<?= $editando ? 'editar' : 'crear' ?>">
          <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int) $editando['id'] ?>"><?php endif; ?>

          <h3><?= $editando ? 'Editar aliado' : 'Nuevo aliado' ?></h3>

          <div class="campo">
            <label for="a_nombre">Nombre del negocio o persona</label>
            <input type="text" id="a_nombre" name="nombre" value="<?= e($editando['nombre'] ?? '') ?>" required>
          </div>

          <div class="campo">
            <label for="a_codigo">Código</label>
            <input type="text" id="a_codigo" name="codigo" value="<?= e($editando['codigo'] ?? '') ?>"
                   placeholder="TIENDAGONZALES" style="text-transform:uppercase" required>
            <p class="ayuda">Solo letras y números. Es lo que escribe el cliente al cotizar.</p>
          </div>

          <div class="campos-2">
            <div class="campo">
              <label for="a_contacto">Contacto</label>
              <input type="text" id="a_contacto" name="contacto" value="<?= e($editando['contacto'] ?? '') ?>">
            </div>
            <div class="campo">
              <label for="a_tel">Teléfono</label>
              <input type="tel" id="a_tel" name="telefono" value="<?= e($editando['telefono'] ?? '') ?>">
            </div>
          </div>

          <div class="campos-2">
            <div class="campo">
              <label for="a_desc">Descuento al cliente (%)</label>
              <input type="number" id="a_desc" name="descuento_pct" step="0.01" min="0" max="100"
                     value="<?= e((string) ($editando['descuento_pct'] ?? '10')) ?>">
            </div>
            <div class="campo">
              <label for="a_com">Comisión por referido (MXN)</label>
              <input type="number" id="a_com" name="comision" step="0.01" min="0"
                     value="<?= e((string) ($editando['comision'] ?? '50')) ?>">
            </div>
          </div>

          <?php if ($editando): ?>
            <label style="display:flex;gap:.5rem;align-items:center;color:var(--text-primary);font-weight:500">
              <input type="checkbox" name="activo" value="1" style="width:auto" <?= $editando['activo'] ? 'checked' : '' ?>>
              Aliado activo
            </label>
          <?php endif; ?>

          <button class="btn btn--rojo btn--bloque" type="submit" style="margin-top:1rem">
            <?= $editando ? 'Guardar cambios' : 'Registrar aliado' ?>
          </button>
          <?php if ($editando): ?>
            <a class="btn btn--plata btn--bloque" style="margin-top:.5rem" href="<?= url('admin/admin.php?view=aliados') ?>">Cancelar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php
    adm_footer();
}

// ---------------------------------------------------------------------
function adm_aliado_detalle(int $id): void
{
    $pdo = db();

    $st = $pdo->prepare('SELECT * FROM aliados WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $aliado = $st->fetch();

    if (!$aliado) {
        flash('error', 'Ese aliado no existe.');
        redirigir('admin/admin.php?view=aliados');
    }

    $st = $pdo->prepare(
        'SELECT r.*, o.folio
         FROM referidos r LEFT JOIN ordenes o ON o.id = r.orden_id
         WHERE r.aliado_id = ? ORDER BY r.creado_en DESC'
    );
    $st->execute([$id]);
    $referidos = $st->fetchAll();

    $deuda   = adm_deuda_aliado($id);
    $pagados = 0;
    foreach ($referidos as $r) {
        if ($r['pagado']) { $pagados++; }
    }

    adm_header($aliado['nombre']);
    adm_titulo(
        $aliado['nombre'],
        'Código ' . $aliado['codigo'],
        '<a class="btn btn--plata btn--sm" href="' . url('admin/admin.php?view=aliados') . '">Volver</a>'
    );
    ?>

    <div class="metricas">
      <div class="metrica"><strong><?= count($referidos) ?></strong><span>Referidos totales</span></div>
      <div class="metrica"><strong><?= $pagados ?></strong><span>Ya pagados</span></div>
      <div class="metrica <?= $deuda > 0 ? 'metrica--alerta' : '' ?>">
        <strong><?= e(number_format($deuda, 0)) ?></strong><span>Pesos por pagar</span>
      </div>
    </div>

    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.5rem">
      <form method="post" style="margin:0">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="sumar">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="volver_a" value="<?= $id ?>">
        <button class="btn btn--rojo btn--sm" type="submit">+1 referido</button>
      </form>

      <?php if ($deuda > 0): ?>
        <form method="post" style="margin:0">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="pagar_todo">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="volver_a" value="<?= $id ?>">
          <button class="btn btn--plata btn--sm" type="submit"
                  data-confirmar="¿Marcar como pagados los <?= e(precio_fmt($deuda)) ?> pendientes?">
            Saldar cuenta
          </button>
        </form>
      <?php endif; ?>

      <?php if ($aliado['telefono']): ?>
        <a class="btn btn--plata btn--sm" target="_blank" rel="noopener"
           href="<?= e(wa_link('¡Hola! Te escribimos de ' . contenido('sitio_nombre', 'CrazyPro-Reparaciones') . ' por el corte de referidos.', '521' . solo_digitos($aliado['telefono']))) ?>">
          Escribirle por WhatsApp
        </a>
      <?php endif; ?>
    </div>

    <?php if (!$referidos): ?>
      <div class="vacio"><h3>Sin referidos todavía</h3><p>Usa el botón +1 cada vez que llegue un cliente de su parte.</p></div>
    <?php else: ?>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead><tr><th>Fecha</th><th>Origen</th><th>Orden</th><th>Comisión</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($referidos as $r): ?>
              <tr>
                <td class="apagado"><?= e(fecha_fmt($r['creado_en'], false)) ?></td>
                <td><?= $r['origen'] === 'codigo' ? 'Código en el sitio' : 'Registro manual' ?></td>
                <td class="folio"><?= e($r['folio'] ?: '—') ?></td>
                <td><?= e(precio_fmt($r['comision'])) ?></td>
                <td>
                  <span class="pastilla" style="color:<?= $r['pagado'] ? '#21C07A' : 'var(--accent-red)' ?>">
                    <span class="pastilla__punto"></span><?= $r['pagado'] ? 'Pagado' : 'Sin pagar' ?>
                  </span>
                </td>
                <td>
                  <div class="acciones-fila">
                    <form method="post" style="margin:0">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="accion" value="<?= $r['pagado'] ? 'despagar' : 'pagar' ?>">
                      <input type="hidden" name="referido_id" value="<?= (int) $r['id'] ?>">
                      <input type="hidden" name="volver_a" value="<?= $id ?>">
                      <button class="btn btn--plata btn--sm" type="submit"><?= $r['pagado'] ? 'Marcar sin pagar' : 'Marcar pagado' ?></button>
                    </form>
                    <form method="post" style="margin:0">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="accion" value="borrar_referido">
                      <input type="hidden" name="referido_id" value="<?= (int) $r['id'] ?>">
                      <input type="hidden" name="volver_a" value="<?= $id ?>">
                      <button class="btn btn--plata btn--sm" type="submit" data-confirmar="¿Eliminar este referido?">Borrar</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif;

    adm_footer();
}
