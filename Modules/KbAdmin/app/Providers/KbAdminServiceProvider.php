<?php

namespace Modules\KbAdmin\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class KbAdminServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'KbAdmin';

    protected string $nameLower = 'kbadmin';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(
            module_path($this->name, 'resources/views'),
            'kbadmin'
        );
    }
}
