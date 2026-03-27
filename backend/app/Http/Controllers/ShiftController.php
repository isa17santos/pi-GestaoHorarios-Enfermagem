<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ShiftController extends Controller
{
    // Returns the full list of shifts with their related schedule, type, and users.
    #[OA\Get(
        path: '/api/shifts',
        operationId: 'listShifts',
        tags: ['Shifts'],
        summary: 'Lista todos os turnos',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de turnos',
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
            Shift::query()
                ->with(['schedule', 'shiftType', 'users'])
                ->get()
        );
    }

    // Returns a single shift by id with its related schedule, type, and users.
    #[OA\Get(
        path: '/api/shifts/{id}',
        operationId: 'showShift',
        tags: ['Shifts'],
        summary: 'Obtém um turno pelo ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID do turno',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Turno encontrado',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 404, description: 'Turno nao encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(
            Shift::query()
                ->with(['schedule', 'shiftType', 'users'])
                ->findOrFail($id)
        );
    }
}
