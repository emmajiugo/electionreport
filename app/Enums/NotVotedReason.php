<?php

namespace App\Enums;

enum NotVotedReason: string
{
    case NameNotOnRegister = 'name_not_on_register';
    case BvasCouldNotAccredit = 'bvas_could_not_accredit';
    case TurnedAwayOrIntimidated = 'turned_away_or_intimidated';
    case VotingClosedBeforeMyTurn = 'voting_closed_before_my_turn';
    case OfficialsDemandedPayment = 'officials_demanded_payment';
    case NoPvc = 'no_pvc';
    case ChoseNotToVote = 'chose_not_to_vote';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NameNotOnRegister => 'My name was not on the voter register',
            self::BvasCouldNotAccredit => 'BVAS could not accredit me',
            self::TurnedAwayOrIntimidated => 'I was turned away or intimidated',
            self::VotingClosedBeforeMyTurn => 'Voting closed before my turn',
            self::OfficialsDemandedPayment => 'Officials or agents demanded money',
            self::NoPvc => 'I did not have my PVC',
            self::ChoseNotToVote => 'I chose not to vote',
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
