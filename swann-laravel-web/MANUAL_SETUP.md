# Manual Docker setup

The browser based installer is removed. Configure and initialize the application from the terminal.

## First setup

From this directory, copy `.env.example` to `.env`, then configure it for the Compose services:

```sh
cp .env.example .env
sed -i \
  -e 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' \
  -e 's/^# DB_HOST=.*/DB_HOST=mysql/' \
  -e 's/^# DB_PORT=.*/DB_PORT=3306/' \
  -e 's/^# DB_DATABASE=.*/DB_DATABASE=swann/' \
  -e 's/^# DB_USERNAME=.*/DB_USERNAME=root/' \
  -e 's/^# DB_PASSWORD=.*/DB_PASSWORD=/' \
  .env

docker compose up --build -d
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --force --no-interaction
docker compose exec laravel.test php artisan db:seed --force --no-interaction
docker compose exec laravel.test php artisan storage:link
docker compose exec laravel.test sh -lc 'touch storage/installed'
```

Keep `.env` local. The Compose network hostname for MySQL is `mysql`.

In local development, sample accounts are created only when `DEMO_SEED_PASSWORD` is set in `.env`; keep that value private. Production seeding skips sample accounts and uploaded-file cleanup. To bootstrap the first production Super Admin, set `ADMIN_BOOTSTRAP_EMAIL` and a unique `ADMIN_BOOTSTRAP_PASSWORD` of at least 16 characters through the deployment secret manager, then run `php artisan db:seed --force --no-interaction` once. The bootstrap is idempotent and does not reset an existing account password. Remove the one-time password from the deployment environment after bootstrap.

## Existing installation

Use `docker compose exec laravel.test php artisan migrate --force --no-interaction` to apply pending migrations. Do not use `migrate:fresh` on a database with data; it drops all tables.

The application is available at <http://localhost:8000>.
