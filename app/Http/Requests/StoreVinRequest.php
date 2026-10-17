<?php

namespace App\Http\Requests;

use App\Models\Election;
use App\Rules\Turnstile;
use App\Support\VoterIdentificationNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVinRequest extends FormRequest
{
    private ?Election $election = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['vin_first', 'vin_last'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => VoterIdentificationNumber::normalize($this->input($field))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vin_first' => ['required', 'string', 'regex:/^[A-Z0-9]{'.VoterIdentificationNumber::PART_LENGTH.'}$/'],
            'vin_last' => ['required', 'string', 'regex:/^[A-Z0-9]{'.VoterIdentificationNumber::PART_LENGTH.'}$/'],
            'turnstile_token' => ['bail', 'required', 'string', new Turnstile($this->ip())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'turnstile_token.required' => 'Complete the security check to continue.',
            'vin_first.required' => 'Enter the first '.VoterIdentificationNumber::PART_LENGTH.' characters of your VIN.',
            'vin_first.regex' => 'Enter the first '.VoterIdentificationNumber::PART_LENGTH.' letters and numbers of your VIN, as printed on your PVC.',
            'vin_last.required' => 'Enter the last '.VoterIdentificationNumber::PART_LENGTH.' characters of your VIN.',
            'vin_last.regex' => 'Enter the last '.VoterIdentificationNumber::PART_LENGTH.' letters and numbers of your VIN, as printed on your PVC.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->election() === null) {
                    $validator->errors()->add('election', 'There is no election open for reports right now.');
                }
            },
        ];
    }

    public function election(): ?Election
    {
        return $this->election ??= Election::current();
    }

    public function vin(): VoterIdentificationNumber
    {
        return VoterIdentificationNumber::fromParts($this->string('vin_first')->value(), $this->string('vin_last')->value());
    }
}
