<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->workspace->public_id,
            'name' => $this->workspace->name,
            'slug' => $this->workspace->slug,
            'role' => $this->role->value,
        ];
    }
}
