<?php

namespace App\Http\Controllers;

use App\Models\ShiftType;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ShiftTypeController extends Controller
{
    #[OA\Get(
        path: '/api/shift-types',
        summary: 'Lista todos os tipos de turno',
        security: [['bearerAuth' => []]],
        tags: ['Shift Types'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tipos de turno devolvidos com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'morning'),
                                    new OA\Property(property: 'start_time', type: 'string', example: '08:00:00'),
                                    new OA\Property(property: 'end_time', type: 'string', example: '16:00:00'),
                                    new OA\Property(property: 'min_nurses', type: 'integer', example: 3),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado'
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        // Return only the fields needed by schedule-building screens.
        $shiftTypes = ShiftType::query()
            ->orderBy('id')
            ->get(['id', 'name', 'start_time', 'end_time', 'min_nurses']);

        return response()->json([
            'data' => $shiftTypes,
        ]);
    }
}
