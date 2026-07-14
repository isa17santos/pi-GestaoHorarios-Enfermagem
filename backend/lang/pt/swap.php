<?php

// Traducoes em portugues para validacao e erros de troca de turnos.
return [
    'offered_shift_ids' => [
        'required' => 'O campo offered_shift_ids é obrigatório.',
        'array' => 'O campo offered_shift_ids deve ser um array.',
        'min' => 'Deve indicar pelo menos um turno oferecido.',
        'integer' => 'Cada ID em offered_shift_ids deve ser um número inteiro.',
        'exists' => 'Um ou mais turnos oferecidos são inválidos ou não pertencem ao utilizador autenticado.',
    ],
    'requested_shift_ids' => [
        'required' => 'O campo requested_shift_ids é obrigatório.',
        'array' => 'O campo requested_shift_ids deve ser um array.',
        'min' => 'Deve indicar pelo menos um turno solicitado.',
        'integer' => 'Cada ID em requested_shift_ids deve ser um número inteiro.',
        'exists' => 'Um ou mais turnos solicitados são inválidos.',
    ],
    'target_user_id' => [
        'required' => 'O campo target_user_id é obrigatório.',
        'integer' => 'O campo target_user_id deve ser um número inteiro.',
        'exists' => 'O utilizador alvo selecionado é inválido.',
    ],
    'notes' => [
        'string' => 'O campo notes deve ser um texto.',
        'max' => 'O campo notes não pode ter mais de 500 caracteres.',
    ],
    'afternoon_night_violation' => 'Vai fazer um turno de tarde seguido de um turno de noite.',
    'validate_offered_shift_not_owned' => 'O turno oferecido deve pertencer ao utilizador autenticado.',
    'validate_requested_owner_not_found' => 'Não foi possível identificar o enfermeiro dono do turno solicitado.',
    'validate_offered_shift_id' => [
        'required' => 'O campo offered_shift_id é obrigatório.',
        'integer' => 'O campo offered_shift_id deve ser um número inteiro.',
        'exists' => 'O turno oferecido selecionado é inválido.',
    ],
    'validate_requested_shift_id' => [
        'required' => 'O campo requested_shift_id é obrigatório.',
        'integer' => 'O campo requested_shift_id deve ser um número inteiro.',
        'exists' => 'O turno solicitado selecionado é inválido.',
    ],
    'offered_shift_too_soon' => 'Não é possível efetuar trocas para turnos do próprio dia ou passados.',
    //'offered_shift_too_soon_old' => 'Os turnos oferecidos devem ter data superior a 2 dias a partir de hoje.',
    'duplicate_request' => 'Já existe um pedido de troca pendente para estes turnos.',
    'unauthorized_action' => 'O utilizador autenticado não pode interagir com esta troca.',
];
