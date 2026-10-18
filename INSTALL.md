# CrazyPro-Reparaciones — Instalación en WAMP (Windows 10)

Entrega 1 de 2. Esta parte deja funcionando la base de datos, el sitio público, el login de clientes (correo y Google) y el panel del cliente. El panel de administrador completo llega en la entrega 2; por ahora `admin/admin.php` es un marcador que solo comprueba que todo conecta bien.

---

## 1. Copiar los archivos

Descomprime la carpeta `CrazyPro-Reparaciones` dentro de:

```
C:\wamp64\www\
```

Debe quedar así: `C:\wamp64\www\CrazyPro-Reparaciones\index.php`

Enciende WAMP y espera a que el ícono se ponga **verde**.

---

## 2. Crear la base de datos

1. Abre `http://localhost/phpmyadmin` (usuario `root`, contraseña vacía por defecto en WAMP).
2. Pestaña **Importar** → elige `database/schema.sql` → **Continuar**.

Eso crea la base `crazypro` con las 12 tablas. Si prefieres consola:

```
C:\wamp64\bin\mysql\mysql8.x.x\bin\mysql.exe -u root -p < C:\wamp64\www\CrazyPro-Reparaciones\database\schema.sql
```

---

## 3. Configurar el `.env`

Copia `.env.example` y renómbralo como `.env` (sin nombre, solo la extensión — Windows lo permite desde el Bloc de notas guardando con comillas: `".env"`).

Para empezar basta con esto:

```
APP_URL=http://localhost/CrazyPro-Reparaciones
APP_DEBUG=true
DB_HOST=127.0.0.1
DB_NAME=crazypro
DB_USER=root
DB_PASS=
SEED_PASS_ALE=escribe-aqui-una-contraseña-larga
SEED_PASS_JMACH=escribe-aqui-otra-contraseña-larga
```

Google y WhatsApp se pueden dejar vacíos: el sitio funciona sin ellos (el botón de Google simplemente no aparece y las notificaciones caen al respaldo por correo / enlace manual).

---

## 4. Sembrar los datos iniciales

Abre en el navegador:

```
http://localhost/CrazyPro-Reparaciones/database/seed.php
```

Crea los administradores `Ale` y `JMACH` (con las contraseñas que pusiste en `.env`, hasheadas), los 7 estados de orden, los 19 servicios del catálogo, los textos editables del sitio y un aliado de ejemplo.

**Inmediatamente después:**
1. Borra las líneas `SEED_PASS_*` del `.env`.
2. Cuando el sitio pase a producción, borra o mueve `database/seed.php`.

El script se puede volver a ejecutar sin duplicar nada.

---

## 5. Probar

| Qué | Dónde |
|---|---|
| Sitio público | `http://localhost/CrazyPro-Reparaciones/` |
| Cotizar | `…/index.php?view=cotizar` |
| Consultar folio | `…/index.php?view=status` |
| Registro de cliente | `…/auth/auth.php?action=registro` |
| Panel de cliente | `…/cliente/cliente.php?view=panel` |
| **Panel de administrador** | `…/admin/acceso-x7k9qz.php` |

Prueba rápida de extremo a extremo: manda una cotización → te redirige a la pantalla de status con el folio (`16Sep2026-01`) → entra al panel de admin y verifica que el contador de órdenes subió.

---

## 6. Extensiones de PHP necesarias

Clic izquierdo en el ícono de WAMP → **PHP → Extensiones PHP**. Deben estar marcadas:

- `pdo_mysql`
- `curl` (necesaria para Google OAuth y la Cloud API de WhatsApp)
- `mbstring`
- `openssl`
- `fileinfo` (para subir imágenes desde el CMS)

---

## 7. Google OAuth (opcional, cuando quieras activarlo)

1. Google Cloud Console → nuevo proyecto.
2. Pantalla de consentimiento de OAuth (externa): nombre de la app, logo, correo de soporte.
3. Credenciales → **ID de cliente de OAuth** → tipo *Aplicación web*.
4. En **URIs de redirección autorizadas** agrega exactamente:
   ```
   http://localhost/CrazyPro-Reparaciones/auth/auth.php?action=google_callback
   ```
   Y en producción, el mismo con tu dominio y `https`.
5. Copia el Client ID y el Client Secret al `.env`. El botón de Google aparece solo.

---

## 8. WhatsApp (opcional)

El brief lo explica bien: la app de WhatsApp Business no puede recibir órdenes de envío desde el sistema. Para notificaciones automáticas hay que dar de alta el número en la **WhatsApp Business Platform (Cloud API)** y llenar `WA_PHONE_ID` y `WA_TOKEN` en el `.env`.

Mientras eso no exista, el sistema:
- envía la notificación por correo, y
- deja registrado el aviso de WhatsApp como *pendiente*, con un enlace `wa.me` y el mensaje ya redactado para que el técnico solo presione Enviar.

No hay que cambiar nada de código para pasar de un modo al otro.

---

## Notas de seguridad ya incorporadas

- Contraseñas con `password_hash()` (bcrypt/Argon2 según PHP). Nunca en texto plano, ni en el código ni en este documento.
- Consultas preparadas reales (`ATTR_EMULATE_PREPARES => false`) en todas las consultas.
- Token CSRF en todos los formularios POST.
- Escape con `e()` en toda salida que viene de la base de datos.
- Login de admin: máximo 5 intentos fallidos por IP cada 15 minutos, mismo mensaje de error siempre y `noindex`.
- `uploads/.htaccess` impide ejecutar PHP en la carpeta de subidas.
- `.env` fuera del repositorio vía `.gitignore`.

En producción falta añadir: HTTPS obligatorio y `APP_DEBUG=false`.

---

## 9. Panel de administrador (entrega 2)

`admin/admin.php` ya es la versión completa. Nueve pantallas:

| Vista | Qué hace |
|---|---|
| **Pendientes** | Lo primero al entrar: órdenes sin revisar, plazos vencidos o a menos de 2 días, pagos pendientes a aliados y servicios más pedidos. |
| **Órdenes** | Listado con búsqueda y filtro por estado; alta de orden nueva con folio automático. |
| **Editar orden** | Datos, diagnóstico, presupuesto, anticipo, descuento, piezas y cambio de estado. |
| **Ticket** | Comprobante y etiqueta en una sola hoja, con botón de imprimir. |
| **Clientes** | Cuentas registradas, canal de aviso y acceso a sus órdenes. |
| **Servicios** | Alta, edición, imagen, precio, orden y pausar/activar sin borrar. |
| **Estados de orden** | Crear, renombrar, recolorear y reordenar. Los protegidos no se borran. |
| **Textos e imágenes** | Todo el contenido del sitio público, más la rotación del slug de acceso. |
| **Aliados** | Alta, código, descuento, comisión, botón +1, marcar pagado/no pagado y saldar cuenta. |

### Cómo se dispara la notificación
Al cambiar el estado, la casilla «Avisar al cliente» viene marcada. El sistema respeta el canal que el cliente eligió y:

- con la Cloud API configurada, envía el WhatsApp solo;
- sin ella, manda el correo y muestra un aviso con un enlace que abre WhatsApp con el mensaje ya escrito.

En ambos casos queda registrado en «Avisos enviados», dentro de la orden.

### Rotar la dirección del panel
En **Textos e imágenes**, al final: el botón renombra físicamente `admin/acceso-xxxxxx.php` y actualiza la clave `admin_slug`. La dirección anterior devuelve 404 de inmediato. Anota la nueva antes de cerrar sesión.
