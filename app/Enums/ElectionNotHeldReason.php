<?php

namespace App\Enums;

enum ElectionNotHeldReason: string
{
    case OfficialsAbsent = 'officials_absent';
    case MaterialsNotDelivered = 'materials_not_delivered';
    case BvasNotWorking = 'bvas_not_working';
    case ViolenceOrInsecurity = 'violence_or_insecurity';
    case UnitRelocatedWithoutNotice = 'unit_relocated_without_notice';
    case Postponed = 'postponed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OfficialsAbsent => 'INEC officials did not show up',
            self::MaterialsNotDelivered => 'Election materials did not arrive',
            self::BvasNotWorking => 'BVAS machine was missing or not working',
            self::ViolenceOrInsecurity => 'Violence, thugs or insecurity',
            self::UnitRelocatedWithoutNotice => 'Polling unit was moved without notice',
            self::Postponed => 'Election was postponed',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $reason): array => ['value' => $reason->value, 'label' => $reason->label()], self::cases());
    }
}
