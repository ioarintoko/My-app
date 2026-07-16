<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WatchlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movie_id' => [
                'required',
                'integer',
                Rule::exists('movies', 'id'),
            ],
            'status' => [
                'required',
                Rule::in(['plan_to_watch', 'watching', 'watched']),
            ],
            'rating' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'movie_id.exists' => 'Movie tidak ditemukan di database.',
            'status.in' => 'Status harus salah satu dari: plan_to_watch, watching, watched.',
        ];
    }
}