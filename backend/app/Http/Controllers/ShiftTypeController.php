<?php

namespace App\Http\Controllers;

use App\Models\ShiftType;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ShiftTypeController extends Controller
{
    // Returns the full list of shift types.
    #[OA\Get(
        path: '/api/shift-types',
        operationId: 'listShiftTypes',
        tags: ['Shift Types'],
        summary: 'Lista todos os tipos de turno',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de tipos de turno',
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
            ShiftType::query()->get()
        );
    }

    // Returns a single shift type by id.
    #[OA\Get(
        path: '/api/shift-types/{id}',
        operationId: 'showShiftType',
        tags: ['Shift Types'],
        summary: 'Obtém um tipo de turno pelo ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID do tipo de turno',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tipo de turno encontrado',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 404, description: 'Tipo de turno nao encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(
            ShiftType::query()->findOrFail($id)
        );
    }
}
