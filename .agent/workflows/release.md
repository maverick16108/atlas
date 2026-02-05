# Workflow: Release

1.  **Tests**: Run full test suite (`vendor/bin/phpunit`).
2.  **Build**: Run `npm run build` for frontend.
3.  **Optimize**: Run `php artisan optimize`.
4.  **Deploy**: Push to main branch / Deploy to Docker registry.
