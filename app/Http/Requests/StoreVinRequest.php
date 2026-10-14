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
        if (is_string($this->input('vin'))) {
            $this->merge(['vin' => VoterIdentificationNumber::normalize($this->input('vin'))]);
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
            'vin' => ['required', 'string', 'regex:/^[A-Z0-9]{'.VoterIdentificationNumber::LENGTH.'}$/'],
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
            'vin.regex' => 'Your VIN should be '.VoterIdentificationNumber::LENGTH.' letters and numbers, as printed on your PVC.',
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

                    return;
                }

                if ($validator->errors()->has('vin')) {
                    return;
                }

                // A voter whose election held may come back to add the result sheet; nothing else is left to do.
                if ($this->election()->reportFrom($this->vin()->hash())?->election_held === false) {
                    $validator->errors()->add('vin', Election::duplicateVinMessage());
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
        return VoterIdentificationNumber::fromInput($this->string('vin')->value());
    }
}
