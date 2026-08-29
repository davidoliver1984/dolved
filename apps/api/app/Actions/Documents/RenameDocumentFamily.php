<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\DocumentGovernanceException;
use App\Models\DocumentFamily;
use App\Models\User;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Illuminate\Support\Facades\DB;

final readonly class RenameDocumentFamily
{
    public function __construct(private RecordDocumentGovernanceAudit $audit) {}

    public function handle(DocumentFamily $family, User $actor, string $name): DocumentFamily
    {
        $name = trim($name);

        if ($name === '') {
            throw new DocumentGovernanceException('A document family name is required.');
        }

        return DB::transaction(function () use ($family, $actor, $name): DocumentFamily {
            $locked = DocumentFamily::query()->lockForUpdate()->findOrFail($family->id);

            if ($locked->name === $name) {
                return $locked;
            }

            $before = ['name' => $locked->name];
            $locked->name = $name;
            $locked->save();
            $this->audit->recordFamily($locked, $actor, 'document_family_renamed', $before, ['name' => $name]);

            return $locked->refresh();
        });
    }
}
