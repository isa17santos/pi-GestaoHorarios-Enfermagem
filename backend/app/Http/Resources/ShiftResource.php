<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'date' => $this->shift_date?->format('Y-m-d'),
            'shift_type' => $this->whenLoaded('shiftType', function (): array {
                return [
                    'id' => (int) $this->shiftType->id,
                    'name' => $this->shiftType->name instanceof \BackedEnum ? $this->shiftType->name->value : $this->shiftType->name,
                    'color' => $this->shiftType->color,
                    'start_time' => $this->shiftType->start_time,
                    'end_time' => $this->shiftType->end_time,
                ];
            }),
            'users' => $this->whenLoaded('users', function (): array {
                return $this->users->map(function ($user): array {
                    return [
                        'id' => (int) $user->id,
                        'name' => $user->name,
                    ];
                })->values()->all();
            }),
        ];
    }
}
