<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'current_state' => [
                'class' => $this->state::class,
                'name' => $this->state::$name,
                'label' => $this->state->label(),
                'description' => $this->state->description(),
                'color' => $this->state->color(),
                'can_be_cancelled' => $this->state->canBeCancelled(),
                'requires_payment' => $this->state->requiresPayment(),
                'is_final' => $this->state->isFinal(),
            ],
            'available_transitions' => collect($this->getAvailableTransitions())->map(function ($stateClass) {
                $state = new $stateClass($this->resource);
                return [
                    'class' => $stateClass,
                    'name' => $state::$name,
                    'label' => $state->label(),
                    'description' => $state->description(),
                    'color' => $state->color(),
                ];
            }),
            'state_history' => collect($this->status_history ?? [])->map(function ($entry) {
                return [
                    'from_state' => $entry['from_state'] ?? $entry['from_status'] ?? null,
                    'to_state' => $entry['to_state'] ?? $entry['to_status'] ?? null,
                    'changed_at' => $entry['changed_at'],
                    'reason' => $entry['reason'] ?? $entry['note'] ?? null,
                ];
            }),
        ];
    }
}
