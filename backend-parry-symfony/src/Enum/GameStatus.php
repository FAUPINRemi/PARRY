<?php
namespace App\Enum;

enum GameStatus: string
{
    case WAITING = 'waiting';
    case IN_PROGRESS = 'in_progress';
    case FINISHED = 'finished';
}