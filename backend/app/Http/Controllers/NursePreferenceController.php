<?php

namespace App\Http\Controllers;

use App\Models\NursePreference;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class NursePreferenceController extends Controller
{
    // Returns the full list of nurse preferences with related user and schedule data.
    #[OA\Get(
        path: '/api/nurse-preferences',
        operationId: 'listNursePreferences',
        tags: ['Nurse Preferences'],
        summary: 'Lista todas as preferencias de enfermagem',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de preferencias',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'object')
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(
            NursePreference::query()
                ->with(['user', 'schedule'])
                ->get()
        );
    }

    // Returns nurse preferences filtered by shift type.
    #[OA\Get(
        path: '/api/nurse-preferences/by-shift-type/{type}',
        operationId: 'listNursePreferencesByShiftType',
        tags: ['Nurse Preferences'],
        summary: 'Lista preferencias filtradas por tipo de turno',
        parameters: [
            new OA\Parameter(
                name: 'type',
                description: 'Tipo de turno preferido',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['morning', 'afternoon', 'night'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de preferencias filtradas',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'object')
                )
            ),
            new OA\Response(response: 404, description: 'Tipo de turno nao suportado'),
        ]
    )]
    public function byShiftType(string $type): JsonResponse
    {
        $column = match ($type) {
            'morning' => 'prefers_morning',
            'afternoon' => 'prefers_afternoon',
            'night' => 'prefers_night',
            default => null,
        };

        abort_unless($column !== null, 404, 'Shift type not supported for preferences.');

        return response()->json(
            NursePreference::query()
                ->with(['user', 'schedule'])
                ->where($column, true)
                ->get()
        );
    }
}
