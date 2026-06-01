<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromptEnvironmentResource extends JsonResource
{
    /** Transform the resource into an array. */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'prompt_id'         => $this->prompt_id,
            'prompt_version_id' => $this->prompt_version_id,
            'environment'       => $this->environment,
            'promoted_at'       => $this->promoted_at,
            'promoted_by'       => $this->promoted_by,
            'version'           => new PromptVersionResource($this->whenLoaded('version')),
        ];
    }
}
