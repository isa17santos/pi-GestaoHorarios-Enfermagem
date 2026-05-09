<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePreferencesRequest extends FormRequest
{
    // Authorization is handled by the auth:sanctum middleware.
    public function authorize(): bool
    {
        return true;
    }

    // Validate the authenticated user's preference payload.
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'prefers_morning' => ['required', 'boolean'],
            'prefers_afternoon' => ['required', 'boolean'],
            'prefers_night' => ['required', 'boolean'],
            'avoid_weekends' => ['required', 'boolean'],
            'prefers_weekends' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_id.required' => 'O campo schedule_id é obrigatório.',
            'schedule_id.integer' => 'O campo schedule_id deve ser um número inteiro.',
            'schedule_id.exists' => 'O schedule_id selecionado é inválido.',
            'prefers_morning.required' => 'O campo prefers_morning é obrigatório.',
            'prefers_morning.boolean' => 'O campo prefers_morning deve ser verdadeiro ou falso.',
            'prefers_afternoon.required' => 'O campo prefers_afternoon é obrigatório.',
            'prefers_afternoon.boolean' => 'O campo prefers_afternoon deve ser verdadeiro ou falso.',
            'prefers_night.required' => 'O campo prefers_night é obrigatório.',
            'prefers_night.boolean' => 'O campo prefers_night deve ser verdadeiro ou falso.',
            'avoid_weekends.required' => 'O campo avoid_weekends é obrigatório.',
            'avoid_weekends.boolean' => 'O campo avoid_weekends deve ser verdadeiro ou falso.',
            'prefers_weekends.required' => 'O campo prefers_weekends é obrigatório.',
            'prefers_weekends.boolean' => 'O campo prefers_weekends deve ser verdadeiro ou falso.',
            'notes.string' => 'O campo notes deve ser um texto.',
            'notes.max' => 'O campo notes não pode ter mais de 500 caracteres.',
        ];
    }
}