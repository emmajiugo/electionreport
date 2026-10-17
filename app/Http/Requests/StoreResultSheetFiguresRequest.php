<?php

namespace App\Http\Requests;

use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Party;
use App\Models\Report;
use App\Models\ResultSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Figures are copied from the sheet as printed, so they are not required to add up: a sheet whose
 * figures don't add up is itself evidence worth keeping.
 */
class StoreResultSheetFiguresRequest extends FormRequest
{
    private const int MAX_FIGURE = 100_000;

    private ?Report $report = null;

    /**
     * Only the voter who uploaded a sheet can type in its figures.
     */
    public function authorize(): bool
    {
        return $this->report()?->election_held === true && $this->sheet() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $figure = ['required', 'integer', 'min:0', 'max:'.self::MAX_FIGURE];
        $partyIds = $this->partyIds();

        return [
            ...array_fill_keys(array_keys(ResultSheet::FIGURES), $figure),
            'party_votes' => ['required', 'array:'.implode(',', $partyIds)],
            ...array_fill_keys(array_map(fn (int $id): string => "party_votes.{$id}", $partyIds), $figure),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'required' => 'Enter this figure. Use 0 if the box on the sheet is empty.',
            'integer' => 'Enter a whole number.',
            'min' => 'Enter a whole number.',
            'max' => 'This number is too large.',
        ];

        // A leading "*" only covers top-level fields, so party votes need their own entries.
        return [
            ...collect($messages)->mapWithKeys(fn (string $message, string $rule): array => [
                "*.{$rule}" => $message,
                "party_votes.*.{$rule}" => $message,
            ])->all(),
            'party_votes.array' => 'Enter votes only for the parties on the ballot.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->sheet()?->hasFigures()) {
                    $validator->errors()->add('figures', self::alreadyRecordedMessage());
                }
            },
        ];
    }

    public function report(): ?Report
    {
        return $this->report ??= Election::current()?->reportFrom($this->session()->get(EnsureVinEntered::REPORT_KEY));
    }

    public function sheet(): ?ResultSheet
    {
        return $this->report()?->resultSheet;
    }

    /**
     * @return array<int, int>
     */
    private function partyIds(): array
    {
        return $this->report()?->election->parties()->get(['parties.id'])
            ->map(fn (Party $party): int => $party->id)
            ->all() ?? [];
    }

    public static function alreadyRecordedMessage(): string
    {
        return 'The figures for your result sheet have already been recorded.';
    }
}
