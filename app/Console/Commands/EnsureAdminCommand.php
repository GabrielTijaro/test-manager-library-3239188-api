<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\EnsureInitialAdminService;
use Illuminate\Console\Command;

/** T-17 · Crea el admin inicial desde ADMIN_USERNAME / ADMIN_PASSWORD. Es idempotente. */
final class EnsureAdminCommand extends Command
{
    protected $signature = 'library:ensure-admin';

    protected $description = 'Crea el administrador inicial desde las variables de entorno si no existe';

    public function handle(EnsureInitialAdminService $service): int
    {
        $created = $service->execute(
            (string) config('library.admin_username'),
            (string) config('library.admin_password'),
        );

        $this->info($created ? 'Administrador inicial creado.' : 'El administrador inicial ya existe.');

        return self::SUCCESS;
    }
}
