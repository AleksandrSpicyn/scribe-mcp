<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp;

use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\Contracts\ScribeOpenApiSource;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\ScribeOpenApiFileSource;
use Illuminate\Support\ServiceProvider;
use Override;

final class ScribeMcpServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/scribe-mcp.php', 'scribe-mcp');
        $this->app->scoped(ScribeOpenApiSource::class, ScribeOpenApiFileSource::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/scribe-mcp.php' => config_path('scribe-mcp.php'),
        ], 'scribe-mcp-config');
    }
}
