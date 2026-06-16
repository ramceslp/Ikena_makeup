# Ikena LMS — Plataforma de Cursos en Video (MVP)

Plataforma de e-learning estilo Udemy, **desacoplada**: una API REST en Laravel y una SPA
en Vue 3 que la consume. La autenticación es por **token Bearer (Sanctum)**, de modo que
la misma API puede servir en el futuro a apps móviles sin cambios.

- **Backend:** PHP 8.4 · Laravel 13 · Sanctum · Socialite (Google OAuth)
- **Base de datos:** MySQL 8.0 (compatible MariaDB, driver `mysql`)
- **Frontend:** Vue 3 (Composition API) · Vite · Pinia · Vue Router · Axios · Tailwind v4

> El contrato técnico completo (esquema, relaciones, endpoints y formas JSON) vive en
> [`ARCHITECTURE.md`](./ARCHITECTURE.md). El sistema de diseño visual, en `design_system.pdf`.

```
Ikena_makeup/
├── backend/          # API Laravel
├── frontend/         # SPA Vue 3
├── ARCHITECTURE.md   # Contrato único de verdad (API + BD)
└── README.md
```

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

Esto crea el esquema y datos de prueba:

| Rol        | Email                   | Password   |
|------------|-------------------------|------------|
| Estudiante | `student@ikena.test`    | `password` |
| Instructor | `instructor@ikena.test` | `password` |

> Inicia sesión como **instructor** para acceder al panel de autoría en `/instructor`
> (crear/editar/publicar cursos, secciones y lecciones).

El estudiante queda inscrito en un curso con 2 lecciones completadas, así el progreso y
"Mis Cursos" muestran datos reales desde el primer arranque.

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

### Públicos
| Método | Ruta                       | Descripción                          |
|--------|----------------------------|--------------------------------------|
| POST   | `/api/register`            | Registro (email/password)            |
| POST   | `/api/login`               | Login (email/password)               |
| POST   | `/api/auth/google`         | Login/registro con `id_token` Google |
| GET    | `/api/courses`             | Catálogo (filtros: search, precio, sort) |
| GET    | `/api/courses/{slug}`      | Detalle público del curso            |

### Protegidos (`Authorization: Bearer <token>`)
| Método | Ruta                            | Descripción                              |
|--------|---------------------------------|------------------------------------------|
| GET    | `/api/me`                       | Usuario autenticado                      |
| POST   | `/api/logout`                   | Revoca el token actual                   |
| GET    | `/api/my-courses`               | Cursos comprados + % de progreso         |
| POST   | `/api/courses/{slug}/enroll`    | Inscripción directa (solo cursos gratis) |
| POST   | `/api/courses/{slug}/checkout`  | Inicia compra de curso pago (crea orden + config de la Cajita) |
| POST   | `/api/payments/confirm`         | Confirma el pago (`{id, clientTransactionId}`) y matricula |
| GET    | `/api/lessons/{id}`             | Video (solo si es gratis o está comprado) |
| POST   | `/api/lessons/{id}/complete`    | Marca/desmarca lección (toggle)          |

### Instructor (`Authorization: Bearer <token>` + rol `instructor`)
Panel de autoría: el instructor gestiona **solo sus propios cursos**. El video no se
almacena en el servidor — solo se guarda una **URL alojada** (YouTube, Vimeo o `.mp4`
directo); el frontend la normaliza a iframe o `<video>` con `src/utils/video.js`.

| Método | Ruta                                                   | Descripción                          |
|--------|--------------------------------------------------------|--------------------------------------|
| GET    | `/api/instructor/courses`                              | Mis cursos (publicados + borradores) |
| POST   | `/api/instructor/courses`                              | Crear curso (nace como borrador)     |
| GET    | `/api/instructor/courses/{slug}`                       | Detalle editable (incluye `video_url`) |
| PATCH  | `/api/instructor/courses/{slug}`                       | Actualizar datos del curso           |
| DELETE | `/api/instructor/courses/{slug}`                       | Eliminar curso (cascada)             |
| POST   | `/api/instructor/courses/{slug}/publish`               | Publicar (422 si tiene 0 lecciones)  |
| POST   | `/api/instructor/courses/{slug}/unpublish`             | Despublicar                          |
| POST   | `/api/instructor/courses/{slug}/sections`              | Crear sección                        |
| PATCH  | `/api/instructor/courses/{slug}/sections/reorder`      | Reordenar secciones (`{ordered_ids}`) |
| PATCH  | `/api/instructor/sections/{id}`                        | Renombrar sección                    |
| DELETE | `/api/instructor/sections/{id}`                        | Eliminar sección (cascada)           |
| POST   | `/api/instructor/sections/{id}/lessons`                | Crear lección                        |
| PATCH  | `/api/instructor/sections/{id}/lessons/reorder`        | Reordenar lecciones (`{ordered_ids}`) |
| PATCH  | `/api/instructor/lessons/{id}`                         | Actualizar lección (incl. `video_url`) |
| DELETE | `/api/instructor/lessons/{id}`                         | Eliminar lección                     |

---

## 5. Verificación rápida (sin frontend)

Con el backend corriendo:

```bash
# Catálogo público
curl http://localhost:8000/api/courses

# Login y captura de token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"student@ikena.test","password":"password"}'

# Mis cursos (reemplaza TOKEN)
curl http://localhost:8000/api/my-courses -H "Authorization: Bearer TOKEN"
```

---

## 6. Notas de arquitectura

- **Autenticación por token (no cookie):** elegido a propósito para que apps móviles
  futuras usen la misma API sin sesiones de navegador.
- **Control de acceso al video:** `GET /api/lessons/{id}` devuelve el `video_url` solo si
  la lección es gratuita **o** el usuario está inscrito en el curso; de lo contrario `403`.
  El catálogo público nunca expone URLs de video de lecciones pagas.
- **Progreso:** `progress_percentage = round(lecciones_completadas / lecciones_totales * 100)`,
  calculado en el backend y reflejado en tiempo real en el reproductor (actualización
  optimista con rollback ante error de red).
- **Sin mocks:** el frontend siempre consume la API real de Laravel.
