<?php

namespace App\Http\Requests;

use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Report;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreResultSheetRequest extends FormRequest
{
    /**
     * The browser shrinks photos to about 1600px before upload; these limits leave headroom while
     * keeping a hostile upload from exhausting memory when it is decoded.
     */
    private const int MAX_KILOBYTES = 2048;

    private const int MAX_PIXELS_PER_SIDE = 2000;

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
        return [
            'result_sheet' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(self::MAX_KILOBYTES)
                    ->dimensions(Rule::dimensions()->maxWidth(self::MAX_PIXELS_PER_SIDE)->maxHeight(self::MAX_PIXELS_PER_SIDE)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result_sheet.required' => 'Take or choose a photo of the result sheet.',
            'result_sheet.image' => 'Upload a photo of the result sheet.',
            'result_sheet.mimes' => 'Upload a photo of the result sheet.',
            'result_sheet.max' => 'This photo is too large. Please try again.',
            'result_sheet.dimensions' => 'This photo is too large. Please try again.',
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
