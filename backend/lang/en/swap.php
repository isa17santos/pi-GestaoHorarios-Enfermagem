<?php

// English translations for swap validation and domain errors.
return [
    'offered_shift_ids' => [
        'required' => 'The offered_shift_ids field is required.',
        'array' => 'The offered_shift_ids field must be an array.',
        'min' => 'At least one offered shift is required.',
        'integer' => 'Each offered shift ID must be an integer.',
        'exists' => 'One or more offered shifts are invalid or do not belong to the authenticated user.',
    ],
    'requested_shift_ids' => [
        'required' => 'The requested_shift_ids field is required.',
        'array' => 'The requested_shift_ids field must be an array.',
        'min' => 'At least one requested shift is required.',
        'integer' => 'Each requested shift ID must be an integer.',
        'exists' => 'One or more requested shifts are invalid.',
    ],
    'target_user_id' => [
        'required' => 'The target_user_id field is required.',
        'integer' => 'The target_user_id field must be an integer.',
        'exists' => 'The selected target user is invalid.',
    ],
    'notes' => [
        'string' => 'The notes field must be a string.',
        'max' => 'The notes field must not be greater than 500 characters.',
    ],
    'offered_shift_too_soon' => 'Offered shifts must be scheduled for more than 2 days from today.',
];
