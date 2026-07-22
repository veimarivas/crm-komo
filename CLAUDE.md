# Komo CRM — CRM de leads estilo Kommo (Laravel 13 + MariaDB)

CRM de ventas centrado en **leads** inspirado en Kommo (kommo.com), hermano del **wacrm** (`C:\xampp_82_12\htdocs\laravel_crm_whatsapp`, CRM de WhatsApp). Son **dos proyectos separados integrados por API**: este maneja leads/tareas/pipeline; el wacrm es el motor de WhatsApp.

## Estado: fases 1 y 2 completadas (2026-07-12)

Suite: **31 tests / 99 aserciones en verde** (`php artisan test`). Usuario de pruebas: `admin@gmail.com` / `admin123` (owner, con pipeline "Ventas" sembrado).

Fase 2 (UI completa, estilo Velzon del wacrm — cards rounded-2xl, gradientes, marca #045474):
- Layout propio con **sidebar fija** (`Layouts/AuthenticatedLayout.jsx`): Dashboard, Leads, Tareas, Contactos, Empresas, Integración.
- `Dashboard` (KPIs: abiertos/ganados mes/tareas hoy/**leads sin tarea** — la métrica Kommo), `Leads/Index` (Kanban drag&drop HTML5, punto rojo = sin tarea pendiente), `Leads/Show` (la página estrella: tabs Timeline/Tareas/Notas, editor de datos, botones Ganado/Perdido, panel de **enviar WhatsApp** vía wacrm), `Tasks/Index` (agenda con tabs pendientes/hoy/vencidas/completadas, completar con nota de resultado), `Contacts/Index` y `Companies/Index` (tablas con modal CRUD), `Settings/Integration` (wizard 2 pasos: credenciales+probar conexión / URL del webhook para pegar en el wacrm).
- Controladores: Dashboard, Lead (index/store/show/update/move/destroy/addNote/**sendWhatsapp**), Task (index/store/complete/destroy), Contact, Company, Integration (edit/update/**test** → llama /api/v1/me del wacrm).

## Entorno

- BD: `laravel_komo_crm` (root sin contraseña, XAMPP). Tests contra `laravel_komo_crm_test` (phpunit.xml).
- Puerto sugerido: **8001** (`php artisan serve --port=8001`) — el wacrm usa el 8000.
- Mismo stack y convenciones que el wacrm: Laravel 13 (atributos `#[Fillable]`), UUIDs, multi-tenant por `account_id` (trait `BelongsToAccount`), Breeze+Inertia+React (npm install requiere `--legacy-peer-deps`; `resources/js/bootstrap.js` creado a mano).

## Modelo de dominio (lo que lo diferencia del wacrm)

- **Lead** = oportunidad con ciclo de vida. `leads.status` (open/won/lost) se **deriva del `stage_type` de su etapa** — cambiar de etapa SOLO vía `Lead::moveToStage()` (valida pipeline, sincroniza status/closed_at, registra eventos won/lost/reopened en el timeline).
- **Pipelines** con etapas tipadas: `stage_type` open|won|lost. El registro siembra "Ventas" con Nuevo/Contactado/Negociación + Ganado(won) + Perdido(lost).
- **lead_events** = timeline del lead (created, stage_changed, won, lost, reopened, task_completed, message_in/out, note_added). Los mensajes de WhatsApp aterrizan aquí.
- **Tasks** con due_at/completed_at; `Task::complete()` registra evento en el lead. Scopes `pending()`/`overdue()`. Regla Kommo: ningún lead sin tarea pendiente (`Lead::hasPendingTask()`).
- **Contacts** (con `phone_normalized` — la clave de correlación con el wacrm — y `wacrm_contact_id`) y **Companies**. Tags y Notes son **polimórficos** (leads/contactos/empresas; pivots con PK compuesta, sin uuid id).

## Integración con el wacrm

Tabla `integrations` (una por cuenta): `wacrm_url`, `wacrm_api_key` (cifrada; scopes contacts/conversations/messages), `webhook_secret` (cifrado).

- **komo → wacrm**: `Services/Wacrm/Client.php` consume `/api/v1` del wacrm (me, contacts, conversations, sendMessage).
- **wacrm → komo**: el wacrm registra un webhook saliente apuntando a `POST /webhooks/wacrm/{accountId}` de aquí (sin CSRF, firma HMAC verificada contra `webhook_secret`). `Services/Wacrm/EventProcessor.php`:
  - `contact.created` → contacto espejo (dedup por phone_normalized).
  - `message.received` → si el contacto no tiene lead ABIERTO, crea uno (source whatsapp, primera etapa open del pipeline default, guarda `wacrm_conversation_id`); el mensaje se registra como `message_in` en el timeline. Un lead won/lost NO se reabre — nace un lead nuevo (regla Kommo).

Cableado manual de la integración: en el wacrm crear API key + webhook saliente (eventos message.received y contact.created, URL = komo `/webhooks/wacrm/{account_id}`); en komo guardar url+api_key+whsec en `integrations`.

## Fase 3 completada (2026-07-12) — suite 37/37 (126 aserciones)

- **Digital Pipeline**: tabla `stage_automations` (acción al ENTRAR a una etapa: send_whatsapp | create_task | add_note, tokens {name} {title} {value} {stage}). `Services/DigitalPipeline/Runner` + `Jobs/RunStageAutomationsJob` (cola). Se dispara desde `Lead::booted()` created (cubre manual/WhatsApp/web form) y desde `moveToStage()`. UI: `Pipelines/Automations.jsx` (botón "⚡ Automatizar" en el Kanban) — acciones agrupadas por etapa, pausar/eliminar, contador de ejecuciones.
- **Web forms**: tabla `web_forms` (token público). Rutas públicas GET/POST `/f/{token}` (blade standalone `webform.blade.php` con CSS inline — embebible por iframe), honeypot `website` + throttle `web-form` 10/min/IP. Cada envío: contacto dedup + lead source web_form en la primera etapa + nota con el mensaje. Admin en `Settings/WebForms.jsx` (URL pública + snippet iframe con copiar).
- **Reportes** (`Reports/Index.jsx`): tasa de conversión, ticket promedio, embudo por etapa (barras con color de etapa), cierres won/lost últimos 6 meses, ranking del equipo del mes.
- Sidebar ganó Reportes y Formularios.

## Fase 4 completada (2026-07-12) — suite 41/41 (149 aserciones)

- **Import masivo del wacrm**: botón "💬 Importar del WhatsApp CRM" en Contactos → `ContactController@importFromWacrm` pagina la API del wacrm (tope 40 páginas), dedup por phone_normalized, completa `wacrm_contact_id` en existentes.
- **Equipo**: `TeamController` (mismo patrón wacrm) — invitaciones por link (hash, 7 días, single-use), roles, expulsar (el expulsado recupera cuenta propia con pipeline vía `Services/AccountProvisioner`, que también usa el registro). UI `Settings/Team.jsx` + `/invite/{token}` (`Invitations/Accept.jsx`). Sidebar ganó "Equipo".
- **Tags en leads**: `TagController` + `leads.tags` sync (filtra ids de otras cuentas silenciosamente). UI en la ficha del lead: chips toggle + creador inline "+ Nueva" (Enter crea, Esc cancela).

## Fase 5 completada (2026-07-12) — suite 44/44 (168 aserciones)

- **Custom fields** por entidad (lead|contact|company): tablas `custom_fields` + `custom_field_values` (pivot polimórfico PK compuesta, sin modelo — se maneja con el trait `HasCustomFields` en Lead/Contact: `customFieldValues()` / `syncCustomFieldValues($map, $entity)` que filtra campos de otras cuentas). UI: `Settings/CustomFields.jsx` (3 tarjetas por entidad, sidebar "Campos"), inputs renderizados en Lead Show y en el modal de Contactos según field_type (text|number|date|select).
- **Transferencia de ownership** (`team.members.transfer`, solo owner, el anterior pasa a admin; botón ⭐ en Team.jsx).
- **Tags en contactos**: modal con chips + eager load en la tabla.

## Fase 6 completada (2026-07-12) — suite 49/49 (185 aserciones) — DESARROLLO CERRADO PARA PRUEBAS

- **Notificaciones in-app**: tabla `app_notifications` (nombre para no chocar con las nativas de Laravel) + `AppNotification::notify()` (guard: nunca notificarse a uno mismo). Disparos: lead asignado (store/update de LeadController), lead nuevo por WhatsApp (EventProcessor → owner), lead nuevo por formulario (WebFormController → owner), tareas vencidas (`tasks:notify-overdue` cada 10 min vía Schedule; dedupe con `tasks.overdue_notified_at` — OJO: debe estar en el #[Fillable]). Campana con badge arriba del sidebar + página Notifications/Index con link al lead. Contador compartido como prop Inertia `unreadNotifications`.
- **Empresas**: tags + custom fields en el modal (Company usa HasCustomFields; CompanyController@syncExtras).
- **Operación local del komo ahora requiere 3 procesos**: `php artisan serve --port=8001` + `queue:work` + `schedule:work` (para tasks:notify-overdue).

## Fase 7 completada (2026-07-15) — suite 55/55 (228 aserciones) — API pública + atribución de anuncios

Activa la integración con **meta_ads** (`C:\xampp_82_12\htdocs\laravel_meta_ads`): atribución ROAS y Lead Ads.

- **`leads.source_ref`** (ad_id de Meta) + `source_url` + `meta_leadgen_id` (unique, idempotencia de Lead Ads). Migración 2026_07_15.
- **EventProcessor** (`message.received` del wacrm): si el mensaje trae `referral` (anuncio Click-to-WhatsApp), el lead nuevo nace con `source_ref`/`source_url` y el evento `created` registra `ad_id`. En leads abiertos existentes solo se escribe si aún no tienen source_ref (la atribución original se preserva).
- **Sistema api.key copiado del wacrm**: tabla `api_keys` (hash SHA-256, prefix `komo_live_`), modelo `ApiKey` (scopes `leads:read` / `leads:write` / `contacts:read` en `ApiKey::SCOPES`), middleware `AuthenticateApiKey` (alias `api.key` en bootstrap/app.php), rate limiter `public-api` 120/min por key.
- **`routes/api.php`** `/api/v1`: `GET /me` (ApiController), `GET /leads` (filtros `?ad_id=` → source_ref, `?source=`, `?status=`; devuelve `{data:[{id,name,status,value_cents,…}], meta}` — el shape que espera el `KomoClient::leadsByAdId` de meta_ads) y `POST /leads` (LeadApiController@store: crea lead source `lead_ad`/`api`, dedup de contacto por phone_normalized, pipeline/etapa del payload o fallback al default, custom_fields → nota, notificación al owner, idempotente por `meta_leadgen_id` — reenvío devuelve 200 con `duplicated:true`).
- **`GET /api/v1/contacts`** (scope `contacts:read`, `ContactApiController`): filtros `?tag_id=` (server-side, incluye tags en la respuesta) y `?q=` — lo usa meta_ads (Fase 7.2) para armar Custom Audiences desde tags del komo.
- **UI**: sección "API keys" en Settings/Team (crear con scopes, secreto `komo_live_…` mostrado UNA vez, revocar) + chip azul "Vino del anuncio X" (con link `ver anuncio ↗` si hay source_url) en la ficha del lead.
- Tests en `PublicApiTest` (auth/scopes, GET con value_cents, POST idempotente, referral→source_ref, CRUD de keys).

Cableado con meta_ads: crear aquí una API key con ambos scopes y pegarla en meta_ads → Ajustes → Integraciones (tarjeta Komo).

## Fase 11 (2026-07-19) — Equipo centralizado — suite 63/63 (284 aserciones)

Fase 7 del Komo Hub: `ProvisionController` acepta `account_id` (uuid existente) + `account_role`. Si llegan, el user se une a la cuenta remota con ese rol sin sembrar pipeline extra; sin ellos, mantiene el comportamiento original (owner + pipeline por defecto via AccountProvisioner). Test `ProvisionMemberTest`.

## Fase 10 (2026-07-19) — Notificaciones consolidadas — suite 62/62 (279 aserciones)

Fase 5 del Komo Hub: **`GET /api/v1/notifications`** (`Api\NotificationApiController`, scope `notifications:read` añadido a `ApiKey::SCOPES`) devuelve las notifs del user dueño de la key con `link_path = /leads/{id}` (o `/notifications` si no hay `lead_id`), soporta `?since=` y `?limit=`. `SsoController@consume` acepta `?next=` (path relativo) para encadenar el salto con un deep-link.

## Fase 9 (2026-07-16) — Provisión del ecosistema — suite 61/61 (274 aserciones)

Fase 3 del Komo Hub: **`POST /api/v1/provision`** (`Api\ProvisionController`, sin api.key) firmado HMAC con `HUB_PROVISION_SECRET` (mismo valor en las 4 apps). Crea user+account con pipeline (AccountProvisioner, idempotente por email), emite API key con scopes y cablea la `Integration` con el wacrm (url+key+webhook_secret que manda el hub). Tests en `ProvisionTest`.

## Fase 8 (2026-07-16) — SSO del ecosistema — suite 58/58 (252 aserciones)

Fase 2 del **Komo Hub** (`C:\xampp_82_12\htdocs\laravel_nuevo_proyecto`, 4º proyecto): `SsoController@consume` (ruta pública `GET /sso/consume`, `APP_ID='komo'`) acepta tokens de un solo uso del hub — firma HMAC con `HUB_SSO_SECRET` (`.env` + `services.hub.sso_secret`, mismo valor en las 4 apps), expiración 60s, nonce anti-replay en cache, login por email. `SESSION_COOKIE=komo_session` en `.env`. Tests en `SsoConsumeTest`.

## Pendiente (futuro, no bloquea)

Email SMTP/IMAP (módulo grande; requiere credenciales reales), calendario visual de tareas, tiempo real con Reverb (hoy sin polling — Inertia recarga por navegación).
