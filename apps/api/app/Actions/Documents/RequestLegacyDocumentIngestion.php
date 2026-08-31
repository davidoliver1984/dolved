<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Support\Imports\LegacyUploadRouteGate;

final readonly class RequestLegacyDocumentIngestion
{
    public function __construct(
        private LegacyUploadRouteGate $gate,
        private RequestDocumentIngestion $requestIngestion,
    ) {}

    public function handle(Document $document, string $correlationId): Document
    {
        $this->gate->assertContinuationAllowed($document);

        return $this->requestIngestion->handle($document, $correlationId);
    }
}
