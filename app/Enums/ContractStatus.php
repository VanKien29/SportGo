<?php

namespace App\Enums;

enum ContractStatus: string
{
    case DRAFT = 'draft';
    case WAITING_SIGNATURE = 'waiting_signature';
    case SIGNED = 'signed';
    case COMPLETED = 'completed';
    case LIQUIDATED = 'liquidated';
    case TERMINATED = 'terminated';
}
