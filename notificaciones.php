<?php
/**
 * CrazyPro-Reparaciones — Notificaciones automáticas.
 *
 * Decide el canal según la preferencia del cliente y envía:
 *   · WhatsApp  → WhatsApp Business Platform (Cloud API de Meta) vía cURL.
 *   · Correo    → mail() de PHP.
 *   · Manual    → si la Cloud API no está configurada, se registra la
 *                 notificación como "pendiente" y el panel muestra un
 *                 botón wa.me con el mensaje ya redactado para que el
 *                 técnico solo presione Enviar (respaldo del brief 4.5).
 *
 * En WAMP, mail() normalmente no está configurado: el envío fallará y
 * quedará registrado como 'fallido' en notificaciones_log sin romper nada.
 */

declare(strict_types=1);

// =====================================================================
// Redacción del mensaje
// =====================================================================

/**
 * Arma el texto que se le envía al cliente cuando cambia el estado.
 */
function notif_texto_estado(array $orden, array $estado, string $comentario = ''): string
{
    $marca  = contenido('sitio_nombre', 'CrazyPro-Reparaciones');
    $nombre = $orden['nombre_contacto'] ?: 'Hola';
    $equipo = trim(($orden['modelo'] ?? '') !== '' ? (string) $orden['modelo'] : 'tu equipo');

    $txt  = "¡Hola, {$nombre}! Actualización de {$marca}.\n\n";
    $txt .= "Folio: {$orden['folio']}\n";
    $txt .= "Equipo: {$equipo}\n";
    $txt .= "Nuevo estado: {$estado['nombre']}\n";

    if ($comentario !== '') {
        $txt .= "\nNota del técnico: {$comentario}\n";
    }

    // Al llegar al estado final se ofrecen las dos formas de entrega.
    if (!empty($estado['es_final']) || stripos($estado['nombre'], 'listo') !== false) {
        $txt .= "\n¿Cómo prefieres recibirlo?\n";
        $txt .= "1) Recoger en el taller (" . contenido('direccion', 'Huehuetoca, Estado de México') . ")\n";
        $txt .= "2) Envío a domicilio — respóndenos y cotizamos el traslado\n";
    }

    $txt .= "\nConsulta el avance cuando quieras: " . url('index.php?view=status&folio=' . rawurlencode($orden['folio']));

    return $txt;
}

/** Enlace wa.me listo para que el técnico envíe el mensaje a mano. */
function notif_link_manual(array $orden, string $mensaje): string
{
    $tel = solo_digitos($orden['tel_contacto'] ?? '');
    if ($tel === '') {
        return wa_link($mensaje);
    }
    if (strlen($tel) === 10) {          // 7299244238 → 521 7299244238
        $tel = '521' . $tel;
    }
    return wa_link($mensaje, $tel);
}

// =====================================================================
// Registro en bitácora
// =====================================================================

function notif_log(?int $ordenId, string $canal, ?string $destino, string $mensaje, string $estado, ?string $respuesta = null): void
{
    $st = db()->prepare(
        'INSERT INTO notificaciones_log (orden_id, canal, destino, mensaje, estado, respuesta)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$ordenId, $canal, $destino, $mensaje, $estado, $respuesta]);
}

// =====================================================================
// WhatsApp Business Platform (Cloud API)
// =====================================================================

function wa_cloud_configurada(): bool
{
    return (bool) env('WA_TOKEN') && (bool) env('WA_PHONE_ID');
}

/**
 * Envía un mensaje por la Cloud API.
 *
 * Meta solo permite texto libre dentro de la ventana de 24 h posteriores
 * a un mensaje del cliente. Fuera de esa ventana hace falta una plantilla
 * aprobada: por eso, si WA_TEMPLATE está definida en .env, se manda como
 * plantilla con el estado y el folio como parámetros.
 *
 * @return array{ok:bool, respuesta:string}
 */
function wa_cloud_enviar(string $telefono, string $mensaje, array $paramsPlantilla = []): array
{
    if (!wa_cloud_configurada()) {
        return ['ok' => false, 'respuesta' => 'Cloud API no configurada (.env: WA_TOKEN, WA_PHONE_ID).'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'respuesta' => 'La extensión cURL de PHP está desactivada.'];
    }

    $telefono = solo_digitos($telefono);
    if (strlen($telefono) === 10) {
        $telefono = '521' . $telefono;
    }

    $version  = (string) env('WA_API_VERSION', 'v20.0');
    $phoneId  = (string) env('WA_PHONE_ID');
    $token    = (string) env('WA_TOKEN');
    $plantilla = env('WA_TEMPLATE');

    if ($plantilla) {
        $componentes = [];
        if ($paramsPlantilla) {
            $parametros = [];
            foreach ($paramsPlantilla as $p) {
                $parametros[] = ['type' => 'text', 'text' => (string) $p];
            }
            $componentes[] = ['type' => 'body', 'parameters' => $parametros];
        }
        $cuerpo = [
            'messaging_product' => 'whatsapp',
            'to'                => $telefono,
            'type'              => 'template',
            'template'          => [
                'name'       => (string) $plantilla,
                'language'   => ['code' => (string) env('WA_TEMPLATE_LANG', 'es_MX')],
                'components' => $componentes,
            ],
        ];
    } else {
        $cuerpo = [
            'messaging_product' => 'whatsapp',
            'to'                => $telefono,
            'type'              => 'text',
            'text'              => ['preview_url' => true, 'body' => $mensaje],
        ];
    }

    $ch = curl_init("https://graph.facebook.com/{$version}/{$phoneId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
    ]);

    $respuesta = curl_exec($ch);
    $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        return ['ok' => false, 'respuesta' => 'cURL: ' . $errorCurl];
    }

    return [
        'ok'        => $codigo >= 200 && $codigo < 300,
        'respuesta' => 'HTTP ' . $codigo . ' ' . substr((string) $respuesta, 0, 900),
    ];
}

// =====================================================================
// Correo
// =====================================================================

function correo_enviar(string $para, string $asunto, string $mensaje): array
{
    $remitente = (string) env('MAIL_FROM', 'no-reply@crazypro-reparaciones.com');
    $nombre    = contenido('sitio_nombre', 'CrazyPro-Reparaciones');

    $cabeceras = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($nombre), $remitente),
        'Reply-To: ' . $remitente,
        'X-Mailer: PHP/' . phpversion(),
    ];

    $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    $ok = @mail($para, $asuntoCodificado, $mensaje, implode("\r\n", $cabeceras));

    return [
        'ok'        => (bool) $ok,
        'respuesta' => $ok ? 'Entregado a mail()' : 'mail() devolvió false (revisa la configuración SMTP del servidor).',
    ];
}

// =====================================================================
// Punto de entrada: notificar un cambio de estado
// =====================================================================

/**
 * Notifica al cliente por el canal que haya elegido.
 *
 * @return array Resumen por canal, para mostrarlo en el panel.
 */
function notificar_cambio_estado(array $orden, array $estado, string $comentario = ''): array
{
    $mensaje  = notif_texto_estado($orden, $estado, $comentario);
    $resultado = ['whatsapp' => null, 'correo' => null, 'link_manual' => null, 'mensaje' => $mensaje];

    // Preferencia del cliente registrado; si la orden es de un visitante
    // sin cuenta, se usa lo que haya dejado en el formulario.
    $canal    = 'ambos';
    $telefono = (string) ($orden['tel_contacto'] ?? '');
    $correo   = (string) ($orden['email_contacto'] ?? '');

    if (!empty($orden['usuario_id'])) {
        $st = db()->prepare('SELECT email, whatsapp, canal_notif FROM usuarios WHERE id = ?');
        $st->execute([$orden['usuario_id']]);
        $u = $st->fetch();
        if ($u) {
            $canal    = $u['canal_notif'];
            $telefono = $u['whatsapp'] ?: $telefono;
            $correo   = $u['email'] ?: $correo;
        }
    }

    $ordenId = (int) $orden['id'];

    // --- WhatsApp -----------------------------------------------------
    if ($canal === 'whatsapp' || $canal === 'ambos') {
        if ($telefono !== '' && wa_cloud_configurada()) {
            $r = wa_cloud_enviar($telefono, $mensaje, [$orden['folio'], $estado['nombre']]);
            notif_log($ordenId, 'whatsapp', $telefono, $mensaje, $r['ok'] ? 'enviado' : 'fallido', $r['respuesta']);
            $resultado['whatsapp'] = $r;
        } elseif ($telefono !== '') {
            // Respaldo manual: queda pendiente y el panel ofrece el enlace.
            $link = notif_link_manual($orden, $mensaje);
            notif_log($ordenId, 'manual', $telefono, $mensaje, 'pendiente', 'Cloud API no configurada; enviar desde wa.me');
            $resultado['link_manual'] = $link;
        }
    }

    // --- Correo -------------------------------------------------------
    if ($canal === 'correo' || $canal === 'ambos') {
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $asunto = 'Tu equipo: ' . $estado['nombre'] . ' · Folio ' . $orden['folio'];
            $r = correo_enviar($correo, $asunto, $mensaje);
            notif_log($ordenId, 'correo', $correo, $mensaje, $r['ok'] ? 'enviado' : 'fallido', $r['respuesta']);
            $resultado['correo'] = $r;
        }
    }

    return $resultado;
}

/** Aviso al cliente de que su cotización quedó registrada. */
function notificar_cotizacion_recibida(array $orden): void
{
    $marca = contenido('sitio_nombre', 'CrazyPro-Reparaciones');
    $texto = "¡Gracias por escribirnos a {$marca}!\n\n"
        . "Registramos tu solicitud con el folio {$orden['folio']}.\n"
        . "Te contactamos en breve con el diagnóstico y el costo.\n\n"
        . "Puedes seguir el avance aquí: " . url('index.php?view=status&folio=' . rawurlencode($orden['folio']));

    $correo = (string) ($orden['email_contacto'] ?? '');
    if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $r = correo_enviar($correo, 'Recibimos tu solicitud · Folio ' . $orden['folio'], $texto);
        notif_log((int) $orden['id'], 'correo', $correo, $texto, $r['ok'] ? 'enviado' : 'fallido', $r['respuesta']);
    }
}
