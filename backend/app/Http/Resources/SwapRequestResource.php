<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SwapRequestResource extends JsonResource
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
            'status' => $this->enumValue($this->status),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', function (): array {
                return [
                    'id' => (int) $this->creator?->id,
                    'name' => $this->creator?->name,
                ];
            }, null),
            'participants' => $this->whenLoaded('participants', function (): array {
                return $this->participants->map(function ($participant): array {
                    return [
                        'id' => (int) $participant->user?->id,
                        'name' => $participant->user?->name,
                        'role' => $this->enumValue($participant->role),
                    ];
                })->values()->all();
            }, []),
            // Split shifts by request entry type while preserving owner and shift metadata.
            'offered_shifts' => $this->whenLoaded('requestShifts', function (): array {
                return $this->mapShiftEntries($this->requestShifts, 'offered');
            }, []),
            'requested_shifts' => $this->whenLoaded('requestShifts', function (): array {
                return $this->mapShiftEntries($this->requestShifts, 'requested');
            }, []),
        ];
    }

    private function mapShiftEntries($entries, string $type): array
    {
        return $entries
            ->filter(fn ($entry): bool => $this->enumValue($entry->type) === $type)
            ->map(function ($entry): array {
                return [
                    'id' => (int) $entry->shift?->id,
                    'date' => $entry->shift?->shift_date?->toDateString(),
                    'shift_type' => [
                        'id'         => (int) $entry->shift?->shiftType?->id,
                        'name'       => $entry->shift?->shiftType?->name instanceof BackedEnum
                            ? $entry->shift?->shiftType?->name->value
                            : $entry->shift?->shiftType?->name,
                        'color'      => $entry->shift?->shiftType?->color,
                        'start_time' => $entry->shift?->shiftType?->start_time,
                        'end_time'   => $entry->shift?->shiftType?->end_time,
                    ],
                    'owner' => [
                        'id' => (int) $entry->owner?->id,
                        'name' => $entry->owner?->name,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
