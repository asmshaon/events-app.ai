<?php

namespace App\Http\Requests;

use App\Services\EventListingService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class EventListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * This backs a JSON data endpoint, so invalid filters must return a 422
     * JSON response rather than redirecting back (the default for web routes).
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['message' => 'Invalid filters.', 'errors' => $validator->errors()], 422)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(EventListingService::STATUSES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'country' => ['nullable', 'string', 'max:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The validated filter subset passed to the listing service.
     *
     * @return array{status: ?string, from: ?string, to: ?string, country: ?string, city: ?string}
     */
    public function filters(): array
    {
        return [
            'status' => $this->validated('status'),
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
            'country' => $this->validated('country'),
            'city' => $this->validated('city'),
        ];
    }
}
