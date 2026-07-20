<?php
namespace App\Enum;

enum WinnerType: string
{
    case PLAYERS = 'players';
    case AI = 'ai';
    case PRO_IA = 'pro_ia';
}