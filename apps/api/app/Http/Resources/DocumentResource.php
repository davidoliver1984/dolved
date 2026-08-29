<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'source_filename' => $this->source_filename,
            'publisher_label' => $this->publisher_label,
            'source_url' => $this->source_url,
            'media_type' => $this->media_type,
            'size_bytes' => $this->size_bytes,
            'source_checksum_sha256' => $this->source_checksum_sha256,
            'checksum_verification_status' => $this->checksum_verification_status->value,
            'checksum_unavailable_reason' => $this->checksum_unavailable_reason?->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
