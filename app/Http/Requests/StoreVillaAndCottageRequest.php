<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVillaAndCottageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow admins to create villas/cottages
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
            'type' => 'required|in:Villa,Cottage,Room',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:500',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:255',
            'capacity' => 'required|integer|min:1',
            'price_per_night' => 'required|numeric|min:0',
            'status' => 'required|in:Available,Booked',
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
            'type.required' => 'The type field is required.',
            'type.in' => 'The type must be Villa, Cottage, or Room.',
            'name.required' => 'The name field is required.',
            'capacity.required' => 'The capacity field is required.',
            'capacity.min' => 'The capacity must be at least 1.',
            'price_per_night.required' => 'The price per night is required.',
            'price_per_night.numeric' => 'The price per night must be a number.',
            'price_per_night.min' => 'The price per night must be at least 0.',
            'status.required' => 'The status field is required.',
            'status.in' => 'The status must be Available or Booked.',
        ];
    }
}

