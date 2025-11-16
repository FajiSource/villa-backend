<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVillaAndCottageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow admins to update villas/cottages
        return $this->user() && $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:Villa,Cottage,Room',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:500',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:255',
            'capacity' => 'sometimes|integer|min:1',
            'price_per_night' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:Available,Booked',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The type must be Villa, Cottage, or Room.',
            'capacity.integer' => 'The capacity must be an integer.',
            'capacity.min' => 'The capacity must be at least 1.',
            'price_per_night.numeric' => 'The price per night must be a number.',
            'price_per_night.min' => 'The price per night must be at least 0.',
            'status.in' => 'The status must be Available or Booked.',
        ];
    }
}

