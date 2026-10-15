<?php

namespace App\Http\Requests;

use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Report;
use App\Support\ResultSheetImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreResultSheetRequest extends FormRequest
{
    private ?Report $report = null;

    /**
     * Only a voter who reported that the election held at their polling unit has a sheet to upload.
     */
    public function authorize(): bool
    {
        return $this->report()?->election_held === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ResultSheetImage::rules(required: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result_sheet.required' => 'Take or choose a photo of the result sheet.',
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
                if ($this->report()?->resultSheet()->exists()) {
                    $validator->errors()->add('result_sheet', self::alreadyUploadedMessage());
                }
            },
        ];
    }

    public function report(): ?Report
    {
        $vinHash = $this->session()->get(EnsureVinEntered::SESSION_KEY.'.hash');

        return $this->report ??= Election::current()?->reportFrom($vinHash);
    }

    public static function alreadyUploadedMessage(): string
    {
        return 'You have already uploaded a result sheet for your polling unit.';
    }
}
