<?php

namespace App\Enums;

/**
 * A voter's judgement of a result sheet uploaded for their polling unit by someone else.
 */
enum ReviewVerdict: string
{
    case Confirm = 'confirm';
    case Dispute = 'dispute';
}
