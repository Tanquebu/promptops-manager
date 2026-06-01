<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestCaseResource extends JsonResource
{
    /** Transform the resource into an array. */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'prompt_id'        => $this->prompt_id,
            'name'             => $this->name,
            'description'      => $this->description,
            'input_variables'  => $this->input_variables,
            'expected_output'  => $this->expected_output,
            'assertion_type'   => $this->assertion_type,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
