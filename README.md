# Buy Overcloud

Plataforma privada multiempresa para operar conversaciones, campañas y agentes de IA sobre WhatsApp Business Cloud API.

## Capacidades

- Acceso únicamente por invitación; no existe registro público.
- Workspaces aislados por cliente para contactos, mensajes, campañas y configuración.
- Conexiones de WhatsApp verificadas contra Meta y tokens cifrados en reposo.
- Creación y envío de plantillas a revisión de Meta.
- Flujos configurables con instrucciones, objetivo, bienvenida y fallback.
- Tarjeta tokenizada con Stripe Checkout en modo `setup`; no se almacena PAN/CVC.
- Consumo de IA registrado por organización.
- Panel responsive con marca Buy Overcloud.

## Desarrollo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Ejecuta las pruebas con `php artisan test`.

## Despliegue en Coolify

El repositorio incluye `Dockerfile`, `docker-compose.yml`, healthcheck HTTP, worker de colas y scheduler. Configura como mínimo:

- `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`
- conexión `DB_*` a MySQL/MariaDB
- `ADMIN_EMAIL` y `ADMIN_PASSWORD`
- `ANTHROPIC_API_KEY`
- `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`

En Meta apunta el webhook a `https://tu-dominio/api/webhook`. En Stripe apunta los eventos a `https://tu-dominio/api/stripe/webhook`.

La migración crea un workspace base y convierte los datos existentes en datos de ese workspace. Los nuevos clientes se crean desde **Clientes** por un usuario `super_admin`.
