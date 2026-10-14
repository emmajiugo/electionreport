<?php

namespace App\Http\Requests;

use App\Enums\ReviewVerdict;
use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Report;
use App\Models\ResultSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResultSheetReviewRequest extends FormRequest
{
    private ?Report $report = null;

    /**
     * A voter can only vouch for or dispute a sheet from the polling unit they reported from, and
     * never their own: that limit is what makes a review mean "I was there and saw this sheet".
     */
    public function authorize(): bool
    {
        $report = $this->report();
        $sheet = $this->sheet();

        return $report?->election_held === true
            && $sheet->election_id === $report->election_id
            && $sheet->polling_unit_id === $report->polling_unit_id
            && $sheet->report_id !== $report->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'verdict' => ['required', Rule::enum(ReviewVerdict::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->report()?->resultSheetReview()->exists()) {
                    $validator->errors()->add('verdict', self::alreadyReviewedMessage());
                }
            },
        ];
    }

    public function report(): ?Report
    {
        $vinHash = $this->session()->get(EnsureVinEntered::SESSION_KEY.'.hash');

        return $this->report ??= Election::current()?->reportFrom($vinHash);
    }

    public function sheet(): ResultSheet
    {
        /** @var ResultSheet */
        return $this->route('sheet');
    }

    public static function alreadyReviewedMessage(): string
    {
        return 'You have already confirmed or disputed a result sheet for your polling unit.';
    }
}
