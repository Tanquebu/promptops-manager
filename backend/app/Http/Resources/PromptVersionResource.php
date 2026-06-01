<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromptVersionResource extends JsonResource
{
    /** Transform the resource into an array. */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'prompt_id'      => $this->prompt_id,
            'version_number' => $this->version_number,
            'content'        => $this->content,
            'status'         => $this->status,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
