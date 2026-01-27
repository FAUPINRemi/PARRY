<?php
namespace App\Enum;

enum RoundStatus: string
{
    case WAITING_QUESTION = 'waiting_question';
    case WAITING_ANSWERS = 'waiting_answers';
    case WAITING_VOTES = 'waiting_votes';
    case COMPLETED = 'completed';
}