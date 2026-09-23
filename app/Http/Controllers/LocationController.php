<?php

namespace App\Http\Controllers;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class LocationController extends Controller
{
    public function lgas(State $state): JsonResponse
    {
        return $this->cachedJson($state->lgas()->orderBy('name')->get(['id', 'name']));
    }

    public function wards(Lga $lga): JsonResponse
    {
        return $this->cachedJson($lga->wards()->orderBy('name')->get(['id', 'name']));
    }

    public function pollingUnits(Ward $ward): JsonResponse
    {
        return $this->cachedJson($ward->pollingUnits()->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn (PollingUnit $unit): array => [
                'id' => $unit->id,
                'name' => "{$unit->code} - {$unit->name}",
            ]));
    }

    /**
     * Location data only changes on re-import, so let browsers cache it to spare the server on election day.
     *
     * @param  Collection<int, mixed>  $options
     */
    private function cachedJson(Collection $options): JsonResponse
    {
        return response()->json($options)->header('Cache-Control', 'public, max-age=3600');
    }
}
