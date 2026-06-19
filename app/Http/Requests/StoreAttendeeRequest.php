<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @return array{name: string, email: string}
     */
    public function attendee(): array
    {
        return [
            'name' => $this->validated('name'),
            'email' => mb_strtolower($this->validated('email')),
        ];
    }
}
