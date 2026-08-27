<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Lead\Models\Lead;

class StageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request
     * @return array
     */
    public function toArray($request)
    {
        $meta = collect(Lead::pipelineStages())->firstWhere('code', $this->code);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'lead_value' => $this->lead_value,
            'formatted_lead_value' => core()->formatBasePrice($this->lead_value),
            'is_user_defined' => $this->is_user_defined,
            'sort_order' => $this->sort_order,
            'kanban_group' => $meta['kanban_group'] ?? null,
            'kanban_group_label' => $meta['kanban_group_label'] ?? null,
            'kanban_group_sort' => $meta['kanban_group_sort'] ?? null,
            'lead_pipeline_id' => $this->lead_pipeline_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
