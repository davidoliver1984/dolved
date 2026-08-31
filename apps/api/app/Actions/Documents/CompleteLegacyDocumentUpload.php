<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Support\Imports\LegacyUploadRouteGate;

final readonly class CompleteLegacyDocumentUpload
{
    public function __construct(
        private LegacyUploadRouteGate $gate,
        private CompleteDocumentUpload $complete,
    ) {}

    public function handle(Document $document): Document
    {
        $this->gate->assertContinuationAllowed($document);

        return $this->complete->handle($document);
    }
}
