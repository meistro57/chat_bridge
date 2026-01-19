<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Chat Bridge Overview

This repository hosts the **Chat Bridge** application built on Laravel + Inertia. It exposes an API/UI served through Nginx and ships a CLI command (`bridge:chat`) that orchestrates conversations between two AI personas while persisting transcripts and embeddings.

## Getting Started

### Prerequisites

- Docker & Docker Compose
- Node.js/npm (handled inside the Dockerfile)

### Run the stack

1. Build and start the containers:

   ```bash
   docker compose up --build
   ```

2. Visit http://localhost:8000 to confirm the UI is reachable (`@routes` indicates Inertia assets built).
3. If you encounter `SQLSTATE[HY000]: General error: 8 attempt to write a readonly database`, run the following on the host before restarting the `app` service:

   ```bash
   chmod 666 database/database.sqlite
   docker compose restart app
   ```

4. The Laravel container will automatically rebuild assets (`composer install` + `npm install && npm run build`) during the Docker build.

5. Populate personas by ensuring `roles.json` exists alongside the repository root (`database/seeders/PersonaSeeder.php` expects `../roles.json`). If the file is missing, the seeder gracefully exits.

## CLI Usage

The `bridge:chat` command can now operate non-interactively:

```bash
docker compose exec app php artisan bridge:chat \
  --max-rounds=0 \
  --persona-a=PERSONA_UUID \
  --persona-b=PERSONA_UUID \
  --starter="Hello from CLI"
```

If persona IDs are omitted, `select` prompts appear and require a TTY.

## Troubleshooting

- Session/database writes rely on `database/database.sqlite` being writable by the container user. Keep the permissions relaxed (e.g., (`chmod 666 database/database.sqlite`)) or add a Docker entrypoint step that ensures the correct ownership/permissions.
- Composer may warn about PSR-4 violations in `tests/certify.php`; adjust the namespace if you intend to run `composer install` without warnings.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
