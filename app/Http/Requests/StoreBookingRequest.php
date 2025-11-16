<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rc_id' => 'required|integer|exists:villas_and_cottages,id',
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'check_in' => 'required|date|after:now',
            'check_out' => 'required|date|after:check_in',
            'pax' => 'required|integer|min:1',
            'special_req' => 'nullable|string|max:1000',
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
            'rc_id.required' => 'The villa/cottage ID is required.',
            'rc_id.exists' => 'The selected villa/cottage does not exist.',
            'name.required' => 'The name field is required.',
            'contact.required' => 'The contact field is required.',
            'check_in.required' => 'The check-in date is required.',
            'check_in.after' => 'The check-in date must be in the future.',
            'check_out.required' => 'The check-out date is required.',
            'check_out.after' => 'The check-out date must be after the check-in date.',
            'pax.required' => 'The number of guests is required.',
            'pax.min' => 'The number of guests must be at least 1.',
        ];
    }
}

