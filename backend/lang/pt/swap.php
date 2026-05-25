<?php

// Traducoes em portugues para validacao e erros de troca de turnos.
return [
    'offered_shift_ids' => [
        'required' => 'O campo offered_shift_ids e obrigatorio.',
        'array' => 'O campo offered_shift_ids deve ser um array.',
        'min' => 'Deve indicar pelo menos um turno oferecido.',
        'integer' => 'Cada ID em offered_shift_ids deve ser um numero inteiro.',
        'exists' => 'Um ou mais turnos oferecidos sao invalidos ou nao pertencem ao utilizador autenticado.',
    ],
    'requested_shift_ids' => [
        'required' => 'O campo requested_shift_ids e obrigatorio.',
        'array' => 'O campo requested_shift_ids deve ser um array.',
        'min' => 'Deve indicar pelo menos um turno solicitado.',
        'integer' => 'Cada ID em requested_shift_ids deve ser um numero inteiro.',
        'exists' => 'Um ou mais turnos solicitados sao invalidos.',
    ],
    'target_user_id' => [
        'required' => 'O campo target_user_id e obrigatorio.',
        'integer' => 'O campo target_user_id deve ser um numero inteiro.',
        'exists' => 'O utilizador alvo selecionado e invalido.',
    ],
    'notes' => [
        'string' => 'O campo notes deve ser um texto.',
        'max' => 'O campo notes nao pode ter mais de 500 caracteres.',
    ],
    'offered_shift_too_soon' => 'Os turnos oferecidos devem ter data superior a 2 dias a partir de hoje.',
];
