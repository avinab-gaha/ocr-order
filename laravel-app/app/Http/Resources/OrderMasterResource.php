<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderMasterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        $data['items'] = OrderDetailResource::collection($this->whenLoaded('details'));
        $data['llm_raw_response'] = $this->llm_raw_response;
        $data['field_confidence'] = $this->field_confidence;
        return $data;
    }
}
