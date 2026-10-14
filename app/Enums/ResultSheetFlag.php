<?php

namespace App\Enums;

/**
 * Evidence that a result sheet deserves a closer look. Flags are shown beside the sheet and never hide
 * it or change its status: each one has innocent explanations, and readers weigh them.
 */
enum ResultSheetFlag: string
{
    case ReusedImage = 'reused_image';
    case EarlyUpload = 'early_upload';
    case ConfirmationBurst = 'confirmation_burst';
    case PartyVotesDontMatchTotal = 'party_votes_dont_match_total';
    case MoreVotesThanAccredited = 'more_votes_than_accredited';

    public function label(): string
    {
        return match ($this) {
            self::ReusedImage => 'The same photo was uploaded for another polling unit',
            self::EarlyUpload => 'Uploaded before polls closed',
            self::ConfirmationBurst => 'Many confirmations arrived together',
            self::PartyVotesDontMatchTotal => 'Party votes do not add up to the total valid votes',
            self::MoreVotesThanAccredited => 'More votes than accredited voters',
        };
    }
}
