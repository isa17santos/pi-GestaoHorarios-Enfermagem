<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShiftType\StoreShiftTypeRequest;
use App\Http\Requests\ShiftType\UpdateShiftTypeRequest;
use App\Http\Resources\ShiftTypeResource;
use App\Models\ShiftType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
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
                                    new OA\Property(property: 'color', type: 'string', example: '#3B82F6'),
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
            ->get(['id', 'name', 'color', 'start_time', 'end_time', 'min_nurses']);

        return ShiftTypeResource::collection($shiftTypes)->response();
    }

    #[OA\Post(
        path: '/api/shift-types',
        summary: 'Cria um novo tipo de turno',
        security: [['bearerAuth' => []]],
        tags: ['Shift Types'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'color', 'start_time', 'end_time', 'min_nurses'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'morning'),
                    new OA\Property(property: 'color', type: 'string', example: '#3B82F6'),
                    new OA\Property(property: 'start_time', type: 'string', example: '08:00:00'),
                    new OA\Property(property: 'end_time', type: 'string', example: '16:00:00'),
                    new OA\Property(property: 'min_nurses', type: 'integer', example: 3),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tipo de turno criado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tipo de turno criado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'morning'),
                                new OA\Property(property: 'color', type: 'string', example: '#3B82F6'),
                                new OA\Property(property: 'start_time', type: 'string', example: '08:00:00'),
                                new OA\Property(property: 'end_time', type: 'string', example: '16:00:00'),
                                new OA\Property(property: 'min_nurses', type: 'integer', example: 3),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para criar tipos de turno'),
            new OA\Response(response: 422, description: 'Payload inválido'),
        ]
    )]
    public function store(StoreShiftTypeRequest $request): JsonResponse
    {
        Gate::authorize('create', ShiftType::class);

        $validated = $request->validated();

        $shiftType = ShiftType::query()->create($validated);

        return (new ShiftTypeResource($shiftType))
            ->additional([
                'message' => 'Tipo de turno criado com sucesso.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Patch(
        path: '/api/shift-types/{shift_type}',
        summary: 'Atualiza um tipo de turno existente',
        security: [['bearerAuth' => []]],
        tags: ['Shift Types'],
        parameters: [
            new OA\Parameter(
                name: 'shift_type',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'color', 'start_time', 'end_time', 'min_nurses'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'afternoon'),
                    new OA\Property(property: 'color', type: 'string', example: '#F59E0B'),
                    new OA\Property(property: 'start_time', type: 'string', example: '16:00:00'),
                    new OA\Property(property: 'end_time', type: 'string', example: '00:00:00'),
                    new OA\Property(property: 'min_nurses', type: 'integer', example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tipo de turno atualizado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tipo de turno atualizado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'afternoon'),
                                new OA\Property(property: 'color', type: 'string', example: '#F59E0B'),
                                new OA\Property(property: 'start_time', type: 'string', example: '16:00:00'),
                                new OA\Property(property: 'end_time', type: 'string', example: '00:00:00'),
                                new OA\Property(property: 'min_nurses', type: 'integer', example: 2),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para atualizar tipos de turno'),
            new OA\Response(response: 404, description: 'Tipo de turno não encontrado'),
            new OA\Response(response: 422, description: 'Payload inválido'),
        ]
    )]
    public function update(UpdateShiftTypeRequest $request, ShiftType $shiftType): JsonResponse
    {
        Gate::authorize('update', $shiftType);

        $validated = $request->validated();

        $shiftType->update($validated);

        return (new ShiftTypeResource($shiftType))
            ->additional([
                'message' => 'Tipo de turno atualizado com sucesso.',
            ])
            ->response();
    }

    #[OA\Delete(
        path: '/api/shift-types/{shift_type}',
        summary: 'Elimina um tipo de turno (soft delete)',
        security: [['bearerAuth' => []]],
        tags: ['Shift Types'],
        parameters: [
            new OA\Parameter(
                name: 'shift_type',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tipo de turno eliminado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tipo de turno eliminado com sucesso.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para eliminar tipos de turno'),
            new OA\Response(response: 404, description: 'Tipo de turno não encontrado'),
        ]
    )]
    public function destroy(ShiftType $shiftType): JsonResponse
    {
        Gate::authorize('delete', $shiftType);

        $shiftType->delete();

        return response()->json([
            'message' => 'Tipo de turno eliminado com sucesso.',
        ]);
    }
}
