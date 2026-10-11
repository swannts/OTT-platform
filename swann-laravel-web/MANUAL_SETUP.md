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

The seed data creates the project's demo administrator account (`admin@swann.com`, password `12345678`). Change that password after the first login.

## Existing installation

Use `docker compose exec laravel.test php artisan migrate --force --no-interaction` to apply pending migrations. Do not use `migrate:fresh` on a database with data; it drops all tables.

The application is available at <http://localhost:8000>.
