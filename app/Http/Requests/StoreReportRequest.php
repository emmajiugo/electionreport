<?php

namespace App\Http\Requests;

use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Support\ResultSheetImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    private ?Election $election = null;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $electionHeld = $this->boolean('election_held');
        $voted = $electionHeld && $this->boolean('voted');

        return [
            'polling_unit_id' => ['required', 'integer', Rule::exists('polling_units', 'id')],
            'election_held' => ['required', 'boolean'],
            'not_held_reason' => $electionHeld
                ? ['prohibited']
                : ['required', Rule::enum(ElectionNotHeldReason::class)],
            'voted' => $electionHeld ? ['required', 'boolean'] : ['prohibited'],
            'not_voted_reason' => $electionHeld && ! $voted
                ? ['required', Rule::enum(NotVotedReason::class)]
                : ['prohibited'],
            'party_id' => $voted
                ? ['required', 'integer', Rule::exists('election_party', 'party_id')->where('election_id', $this->election()?->id)]
                : ['prohibited'],
            'reason_details' => [
                Rule::requiredIf(fn (): bool => $this->input('not_held_reason') === ElectionNotHeldReason::Other->value
                    || $this->input('not_voted_reason') === NotVotedReason::Other->value),
                'nullable',
                'string',
                'max:500',
            ],
            ...$electionHeld
                ? ResultSheetImage::rules(required: false)
                : ['result_sheet' => ['prohibited'], 'sample_result_sheet' => ['prohibited']],
            // Honeypot: invisible to people, bots tend to fill every field.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party_id.exists' => 'Select one of the parties on the ballot.',
            'reason_details.required' => 'Tell us what happened.',
            ...ResultSheetImage::messages('result_sheet'),
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

                // Re-checked here because the VIN may have reported from another tab since it was entered.
                if ($this->election()->hasReportFrom($this->vinHash())) {
                    $validator->errors()->add('vin', Election::duplicateVinMessage());
                }
            },
        ];
    }

    public function election(): ?Election
    {
        return $this->election ??= Election::current();
    }

    public function vinHash(): string
    {
        return $this->session()->get(EnsureVinEntered::SESSION_KEY.'.hash');
    }

    public function maskedVin(): string
    {
        return $this->session()->get(EnsureVinEntered::SESSION_KEY.'.masked');
    }
}
