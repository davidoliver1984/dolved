<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SavedViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $opened = $this->resource->openedDefinition();

        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'definition_schema_version' => $this->definition_schema_version,
            'definition' => $opened['definition'],
            'notices' => $opened['notices'],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
