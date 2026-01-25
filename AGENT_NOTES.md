# Agent Notes

- Dashboard cards updated to glassier styling with circular logo badges.
- Postgres data persisted via bind mount `storage/postgres` in `docker-compose.yml`.
- Added `.gitignore` entries for `storage/debugbar` and `storage/postgres`.
- Docker rebuild/run performed with `docker compose build` and `docker compose up -d`.
- Default seeded admin login: `admin` / `password`.
