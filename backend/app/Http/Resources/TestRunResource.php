<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestRunResource extends JsonResource
{
    /** Transform the resource into an array. */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'test_case_id'      => $this->test_case_id,
            'prompt_version_id' => $this->prompt_version_id,
            'status'            => $this->status,
            'llm_response'      => $this->llm_response,
            'evaluation_result' => $this->evaluation_result,
            'error_message'     => $this->error_message,
            'started_at'        => $this->started_at,
            'completed_at'      => $this->completed_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
