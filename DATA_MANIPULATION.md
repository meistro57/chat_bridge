# Chat Bridge Development Notes

## Database Configuration (Fixed)
The project is now configured to use **PostgreSQL** as the single source of truth for both Docker and local development.

- **Environment**: `.env` is set to `DB_CONNECTION=pgsql`.
- **Docker**: The app container connects to the `postgres` service (via `docker-compose.yml` override).
- **Local**: Local CLI commands connect to `127.0.0.1` (localhost) via the mapped port 5432.

## How to Manipulate Data

### Option A: Local CLI (Recommended if you have PHP/PgSQL installed)
If your local PHP has the `pgsql` extension, you can run commands normally:
```bash
php artisan migrate
php artisan db:seed --class=PersonaSeeder
php artisan tinker
```

### Option B: Docker Exec (Always works)
If you don't have local PHP extensions, run commands inside the container:
```bash
docker exec chatbridge-app php artisan migrate
docker exec chatbridge-app php artisan db:seed --class=PersonaSeeder
docker exec -it chatbridge-app php artisan tinker
```

## Shared Cache Warning
The `bootstrap/cache` directory is shared. If you run `composer` or `package:discover` locally, ensure your environment matches the container (packages) to avoid "Class not found" errors.
