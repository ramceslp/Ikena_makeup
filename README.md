# Ikena Makeup — Plataforma Integral de Belleza y Educación

**Una plataforma todo-en-uno para escuelas de belleza, estéticas y maquilladores profesionales.**

Ikena permite crear y vender **cursos en video**, ofrecer **servicios con agendamiento de citas**, gestionar una **tienda de productos**, compartir **contenido en blog**, y administrar todo desde un **panel de control unificado**. Disponible en navegador web y **app Android nativa**.

### Stack Tecnológico

- **Backend:** PHP 8.4 · Laravel 13 · Sanctum (Bearer tokens) · Socialite (Google OAuth)
- **Base de datos:** MySQL 8.0 (compatible MariaDB)
- **Frontend Web:** Vue 3 (Composition API) · Vite · Pinia · Vue Router · Tailwind v4
- **App Móvil:** Capacitor + WebView (Android, app ID: `com.ikenamakeup.app`)

### Estructura del Proyecto

```
Ikena_makeup/
├── backend/          # API REST Laravel
├── frontend/         # SPA Vue 3 (web + Capacitor WebView)
├── App/              # App Android (Capacitor)
├── ARCHITECTURE.md   # Contrato técnico (BD, endpoints, recursos JSON)
└── README.md
```

> 📖 El contrato técnico completo está en [`ARCHITECTURE.md`](./ARCHITECTURE.md)

---

## ✨ Funcionalidades Principales

### 🎓 Estudiantes / Clientes
- Explorar y comprar cursos en video
- Ver lecciones, marcar progreso y obtener certificados
- Agendar servicios (asesorías, sesiones de maquillaje, etc.) con horarios disponibles
- Comprar productos de la tienda
- Recibir notificaciones push de recordatorios y promociones
- Perfil personal: mis cursos, mis citas, historial de compras

### 👨‍🏫 Instructores
- Crear y publicar cursos con secciones y lecciones
- Subir videos (YouTube, Vimeo, o directo)
- Ver inscripciones y progreso de estudiantes
- Leer reseñas de sus cursos
- Dashboard con analytics de sus cursos

### 🔧 Administradores
- **Gestión de cursos:** crear, editar, publicar, ver todos los cursos de la plataforma
- **Servicios:** crear servicios (consultas, sesiones de maquillaje, etc.) con precio, duración y depósito
- **Disponibilidad:** definir días/horarios abiertos, bloquear fechas no disponibles
- **Productos:** crear catálogo, subir imágenes, controlar inventario
- **Citas:** ver todas las reservas de servicios, confirmar pagos, cancelar
- **Órdenes:** ver todas las compras (cursos, servicios, productos)
- **Blog:** publicar artículos y noticias
- **Certificados:** personalizar diseño, logo, firma digital
- **Notificaciones push:** enviar mensajes personalizados a usuarios en la app

### 📱 Acceso a la Plataforma
- **Web:** Sitio completo en navegador (desktop/mobile)
- **App Android:** Aplicación nativa con todas las funcionalidades, push notifications y acceso sin internet a ciertos contenidos

---

## Requisitos previos

| Herramienta | Versión usada |
|-------------|---------------|
| PHP         | 8.4           |
| Composer    | 2.x           |
| Node / npm  | 24.x / 11.x   |
| MySQL       | 8.0 (o MariaDB 10.6+) |

---

## 1. Backend (Laravel API)

```bash
cd backend
composer install            # dependencias PHP (ya instaladas en este repo)
```

### 1.1 Configurar la base de datos

Crea la base de datos (si no existe):

```sql
CREATE DATABASE lms_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edita **`backend/.env`** y ajusta las credenciales de MySQL. **IMPORTANTE:** debes poner
aquí tu contraseña de MySQL — sin ella las migraciones y el servidor fallarán con
`Access denied for user 'root'`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lms_platform
DB_USERNAME=root
DB_PASSWORD=tu_password_de_mysql      # <-- COMPLETAR
```

### 1.2 Migrar y sembrar datos de ejemplo

```bash
php artisan migrate:fresh --seed
```

Esto crea el esquema completo (cursos, servicios, productos, órdenes, citas, etc.) y datos de prueba:

| Rol        | Email                   | Password   | Rol                                                  |
|------------|-------------------------|-----------|------------------------------------------------------|
| Estudiante | `student@ikena.test`    | `password` | Acceso a cursos, servicios, productos               |
| Instructor | `instructor@ikena.test` | `password` | Panel para crear/editar/publicar cursos             |
| Administrador | `admin@ikena.test`  | `password` | Panel para gestionar TODO (cursos, servicios, etc.) |

El estudiante queda pre-inscrito en un curso con 2 lecciones completadas, así puedes ver
datos reales de progreso, citas, etc. desde el primer arranque.

### 1.3 Levantar el servidor backend

```bash
php artisan serve --port=8000
# API disponible en http://localhost:8000/api
```

---

## 2. Frontend (Vue 3 SPA)

```bash
cd frontend
npm install                 # dependencias (ya instaladas en este repo)
```

Configura **`frontend/.env`**:

```dotenv
VITE_API_URL=http://localhost:8000/api
VITE_GOOGLE_CLIENT_ID=          # (opcional) tu Google OAuth Client ID
```

Levanta el servidor de desarrollo:

```bash
npm run dev
# SPA disponible en http://localhost:5173
```

> Orden de arranque: primero el backend (puerto 8000), luego el frontend (puerto 5173).
> El CORS ya está configurado para `http://localhost:5173`.

---

## 3. Google OAuth (login con Google)

El flujo real ya está implementado (frontend envía el `id_token` a
`POST /api/auth/google`, que lo valida con Socialite, vincula/crea el usuario por
`google_id` y emite un token Sanctum). Solo falta configurar credenciales:

1. Ve a [Google Cloud Console](https://console.cloud.google.com/) → **APIs & Services →
   Credentials → Create OAuth client ID** (tipo *Web application*).
2. En **Authorized JavaScript origins** agrega `http://localhost:5173`.
3. Copia el **Client ID** y **Client Secret**.
4. Backend `backend/.env`:
   ```dotenv
   GOOGLE_CLIENT_ID=tu_client_id
   GOOGLE_CLIENT_SECRET=tu_client_secret
   GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
   ```
5. Frontend `frontend/.env`:
   ```dotenv
   VITE_GOOGLE_CLIENT_ID=tu_client_id
   ```

Sin estas credenciales, el botón de Google aparece deshabilitado; **todo lo demás
(registro/login por email, catálogo, compra, progreso, reproductor) funciona igual**.

---

## 3.b Pasarela de pago — PayPhone (Ecuador)

Los cursos pagos se compran vía **PayPhone (Cajita de Pagos v2.0)** detrás de una capa de
abstracción (`PaymentGatewayInterface`), de modo que cambiar/añadir pasarela = un driver
nuevo, sin tocar la lógica de negocio. Detalle completo en [`PAYMENTS.md`](./PAYMENTS.md).

El MVP arranca con el driver **`fake`** (simulado) para probar todo el flujo de compra sin
credenciales. Variables en `backend/.env`:

```dotenv
PAYMENT_DRIVER=fake          # cambia a 'payphone' en producción
PAYPHONE_TOKEN=              # PayPhone Business -> Developer -> app tipo API
PAYPHONE_STORE_ID=           # dashboard de PayPhone Business
# PAYPHONE_CONFIRM_URL tiene default; no es obligatorio
```

Frontend `frontend/.env`: `VITE_PAYMENT_CALLBACK_URL=http://localhost:5173/payment/callback`
(con fallback automático a `${origin}/payment/callback`).

Flujo: curso pago → `/checkout` crea orden pendiente y devuelve la config de la Cajita →
el frontend renderiza PayPhone → al pagar, PayPhone redirige a `/payment/callback?id=...&clientTransactionId=...`
→ `/payments/confirm` valida con PayPhone (`statusCode 3 = aprobado`) y crea la matrícula
(idempotente). Cursos gratis (precio 0) usan `/enroll` directo.

> Nota Windows: si pasas credenciales por variable de entorno en vez de `.env`, el servidor
> embebido de PHP no las propaga (PHP no puebla `$_ENV` con `variables_order=GPCS`). La
> solución correcta es ponerlas en `.env` — ahí dotenv las carga sin problema.

## 4. Endpoints de la API

> 📖 **Referencia completa:** Ver [`ARCHITECTURE.md`](./ARCHITECTURE.md) para el esquema de BD, modelos, y todos los recursos JSON.

### Públicos
| Categoría | Descripción |
|-----------|-------------|
| **Autenticación** | `POST /api/register`, `POST /api/login`, `POST /api/auth/google` |
| **Cursos** | `GET /api/courses` (catálogo con filtros), `GET /api/courses/{slug}` (detalle) |
| **Servicios** | `GET /api/services` (catálogo), `GET /api/services/{slug}` (detalle) |
| **Agendamiento** | `GET /api/services/{id}/available-days`, `GET /api/services/{id}/available-slots` |
| **Productos** | `GET /api/products` (catálogo), `GET /api/products/{slug}` (detalle) |
| **Blog** | `GET /api/posts`, `GET /api/posts/latest`, `GET /api/posts/{slug}` |

### Protegidos (`Authorization: Bearer <token>`)
| Categoría | Descripción |
|-----------|-------------|
| **Perfil** | `GET /api/me`, `POST /api/logout`, `POST /api/profile` (actualizar), `GET /api/profile/orders`, `GET /api/profile/appointments` |
| **Cursos** | `GET /api/my-courses`, `POST /api/courses/{slug}/enroll`, `POST /api/courses/{slug}/checkout` |
| **Servicios** | `POST /api/bookings` (agendar cita), ver citas en `/api/profile/appointments` |
| **Productos** | `POST /api/cart/checkout` (compra de productos) |
| **Pagos** | `POST /api/payments/confirm` (confirmar pago de curso/servicio/producto) |
| **Notificaciones** | `POST /api/device-tokens` (registrar token para push notifications), `DELETE /api/device-tokens` |
| **Certificados** | `GET /api/courses/{slug}/certificate` (descargar), `GET /api/certificates/verify/{code}` (verificar) |

### 👨‍🏫 Instructor (`Authorization: Bearer <token>` + rol `instructor`)
El instructor gestiona **solo sus propios cursos** en `/api/instructor/*`

| Endpoint Ejemplo | Descripción |
|-----------------|-------------|
| `GET /api/instructor/dashboard` | Analytics de mis cursos |
| `GET /api/instructor/courses` | Mis cursos (publicados + borradores) |
| `POST /api/instructor/courses` | Crear curso |
| `PATCH /api/instructor/courses/{slug}` | Editar curso |
| `POST /api/instructor/courses/{slug}/sections` | Agregar sección |
| `POST /api/instructor/sections/{id}/lessons` | Agregar lección con video |

> 💡 Videos: no se almacenan en servidor, solo URLs alojadas (YouTube, Vimeo, directo `.mp4`)

### 🔧 Admin (`Authorization: Bearer <token>` + rol `admin`)
El admin gestiona **toda la plataforma** en `/api/admin/*`

| Módulo | Funciones |
|--------|-----------|
| **Cursos** | Ver/crear/editar/eliminar todos los cursos (incluso de otros instructores) |
| **Servicios** | CRUD completo de servicios, subir imágenes, reordenar |
| **Productos** | CRUD completo, gestionar inventario, subir imágenes |
| **Blog** | CRUD de posts, imágenes de portada e inline |
| **Citas** | Ver todas las reservas de servicios, marcar como pagadas, cancelar |
| **Órdenes** | Ver todas las compras (cursos, servicios, productos) |
| **Certificados** | Personalizar diseño, logo, firma digital |
| **Notificaciones Push** | Enviar mensajes a usuarios, historial de envíos |
| **Disponibilidad** | Definir bloqueos de agenda para servicios |

---

## 5. Verificación rápida (sin frontend)

Con el backend corriendo en `http://localhost:8000`:

```bash
# Catálogo público (cursos, servicios, productos)
curl http://localhost:8000/api/courses
curl http://localhost:8000/api/services
curl http://localhost:8000/api/products

# Login y obtener token (reemplaza con credenciales de arriba)
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"student@ikena.test","password":"password"}' | jq -r '.token')

# Mis cursos, servicios, órdenes
curl http://localhost:8000/api/my-courses -H "Authorization: Bearer $TOKEN"
curl http://localhost:8000/api/profile/appointments -H "Authorization: Bearer $TOKEN"
curl http://localhost:8000/api/profile/orders -H "Authorization: Bearer $TOKEN"

# Admin: ver todas las citas (solo si eres admin)
curl http://localhost:8000/api/admin/appointments -H "Authorization: Bearer $TOKEN"
```

---

## 6. Notas de arquitectura

### Autenticación & Seguridad
- **Token Bearer (Sanctum):** no cookies, así la API sirve a web + apps móviles sin cambios
- **Roles:** `student` (por defecto), `instructor` (crea cursos), `admin` (gestiona todo)
- **Ownership checks:** instructores solo ven/editan sus cursos; admins ven/editan todo
- **Control de acceso a videos:** GET `/api/lessons/{id}` devuelve `video_url` solo si la lección es gratis O el usuario está inscrito (403 de lo contrario)

### Módulos Principales
- **Cursos:** LMS con secciones, lecciones, progreso, certificados
- **Servicios:** agendamiento de citas con horarios disponibles, depósitos, notificaciones
- **Productos:** tienda con inventario, imágenes, órdenes
- **Blog:** posts con portada e imágenes inline, búsqueda
- **Pagos:** integración PayPhone (Ecuador), manejo de órdenes y depósitos

### Frontend & Apps
- **Web (SPA Vue 3):** acceso completo en navegador
- **App Android (Capacitor):** misma SPA en WebView + notificaciones push + acceso sin conexión parcial
- **Sin mocks:** frontend siempre consume la API real de Laravel

### Performance & UX
- **Progreso en tiempo real:** `progress_percentage` se actualiza optimistamente con rollback ante error
- **Agendamiento inteligente:** slots disponibles calculados en backend, bloques de unavailability configurables
- **Notificaciones push:** device tokens registrados, histórico de envíos, broadcast o segmentado
