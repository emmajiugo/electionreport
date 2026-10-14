<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResultSheetFiguresRequest;
use App\Models\ResultSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ResultSheetFiguresController extends Controller
{
    public function store(StoreResultSheetFiguresRequest $request): RedirectResponse
    {
        /** @var ResultSheet $sheet */
        $sheet = $request->sheet();

        $figures = array_map('intval', $request->safe()->only(array_keys(ResultSheet::FIGURES)));
        $partyVotes = array_map('intval', $request->validated('party_votes'));

        // Conditional on no figures yet, so two submissions racing past the validator cannot both write.
        $recorded = ResultSheet::query()
            ->whereKey($sheet->id)
            ->whereNull('figures_recorded_at')
            ->update([
                ...$figures,
                'party_votes' => json_encode($partyVotes),
                'figures_recorded_at' => now(),
            ]);

        if ($recorded === 0) {
            throw ValidationException::withMessages(['figures' => StoreResultSheetFiguresRequest::alreadyRecordedMessage()]);
        }

        return to_route('result-sheets.create');
    }
}
