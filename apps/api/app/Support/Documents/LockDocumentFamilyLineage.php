<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\Document;
use App\Models\DocumentFamily;
use Illuminate\Database\Eloquent\Collection;

final class LockDocumentFamilyLineage
{
    /** @return array{DocumentFamily, Document, Collection<int, Document>} */
    public function handle(Document $document): array
    {
        $family = DocumentFamily::query()->lockForUpdate()->findOrFail($document->document_family_id);
        $versions = Document::query()
            ->where('document_family_id', $family->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $target = $versions->firstWhere('id', $document->id);

        abort_unless($target instanceof Document, 404);

        return [$family, $target, $versions];
    }
}
