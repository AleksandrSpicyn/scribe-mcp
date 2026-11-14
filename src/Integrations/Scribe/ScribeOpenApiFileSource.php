<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Integrations\Scribe;

use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\Contracts\ScribeOpenApiSource;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

final class ScribeOpenApiFileSource implements ScribeOpenApiSource
{
    private ?string $fingerprint = null;

    private ?ApiDocumentationCatalogDto $catalog = null;

    public function __construct(
        private readonly Filesystem $files,
        private readonly ScribeOpenApiParser $parser,
        private readonly ScribeOpenApiCatalogFactory $catalogFactory,
        #[Config('scribe-mcp.openapi_path')]
        private readonly string $openApiPath,
    ) {
    }

    /** @throws ApiDocumentationUnavailableException */
    public function read(): ApiDocumentationCatalogDto
    {
        $this->ensureArtifactExists();

        try {
            $contents = $this->files->get($this->openApiPath);
        } catch (FileNotFoundException $exception) {
            throw new ApiDocumentationUnavailableException(
                'OpenAPI Scribe не найден. Выполните php artisan scribe:generate.',
                $exception->getCode(),
                previous: $exception,
            );
        }

        $fingerprint = hash('sha256', $contents);

        if ($fingerprint === $this->fingerprint && $this->catalog !== null) {
            return $this->catalog;
        }

        $this->catalog = $this->catalogFactory->make(
            document: $this->parser->parse($contents),
            artifact: $this->artifactPath(),
            fingerprint: $fingerprint,
        );
        $this->fingerprint = $fingerprint;

        return $this->catalog;
    }

    /** @throws ApiDocumentationUnavailableException */
    private function ensureArtifactExists(): void
    {
        if (!$this->files->exists($this->openApiPath)) {
            throw new ApiDocumentationUnavailableException('OpenAPI Scribe не найден. Выполните php artisan scribe:generate.');
        }
    }

    private function artifactPath(): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($this->openApiPath, $basePath)
            ? substr($this->openApiPath, strlen($basePath))
            : $this->openApiPath;
    }
}
