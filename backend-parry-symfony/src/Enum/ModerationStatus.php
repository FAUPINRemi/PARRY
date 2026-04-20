<?php
namespace App\Enum;

enum ModerationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case FLAGGED = 'flagged';
}