<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $movieId = $this->route('movie')?->id;

        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'genre' => 'nullable|string|max:100',
            'duration' => 'nullable|integer|min:1',
            'release_date' => 'nullable|date',
            'rating' => 'nullable|numeric|min:0|max:10',
        ];
    }
}