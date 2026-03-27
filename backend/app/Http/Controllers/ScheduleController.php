<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ScheduleController extends Controller
{
    // Returns the full list of schedules with their main relationships.
    #[OA\Get(
        path: '/api/schedules',
        operationId: 'listSchedules',
        tags: ['Schedules'],
        summary: 'Lista todos os horarios',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de horarios',
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
            Schedule::query()
                ->with(['creator', 'users', 'shifts.shiftType'])
                ->get()
        );
    }

    // Returns a single schedule by id with its main relationships.
    #[OA\Get(
        path: '/api/schedules/{id}',
        operationId: 'showSchedule',
        tags: ['Schedules'],
        summary: 'Obtém um horario pelo ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID do horario',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Horario encontrado',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 404, description: 'Horario nao encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(
            Schedule::query()
                ->with(['creator', 'users', 'shifts.shiftType'])
                ->findOrFail($id)
        );
    }
}
