<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Gestao de Horarios API',
    description: 'Documentacao OpenAPI da API de gestao de horarios de enfermagem.'
)]
#[OA\Tag(
    name: 'Users',
    description: 'Operacoes relacionadas com utilizadores.'
)]
#[OA\Tag(
    name: 'Shift Types',
    description: 'Operacoes relacionadas com tipos de turno.'
)]
#[OA\Tag(
    name: 'Nurse Preferences',
    description: 'Operacoes relacionadas com preferencias de enfermagem.'
)]
#[OA\Tag(
    name: 'Schedules',
    description: 'Operacoes relacionadas com horarios.'
)]
#[OA\Tag(
    name: 'Shifts',
    description: 'Operacoes relacionadas com turnos.'
)]
class OpenApiSpec
{
}
