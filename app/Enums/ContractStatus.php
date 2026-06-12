<?php

namespace App\Enums;

enum ContractStatus: string
{
    case DRAFT = 'draft';
    case GENERATED = 'generated';
    case PENDING_OWNER_SIGNATURE = 'pending_owner_signature';
    case PENDING_SPORTGO_SIGNATURE = 'pending_sportgo_signature';
    case SIGNED_ACTIVE = 'signed_active';
    case CANCELLED = 'cancelled';
    case TERMINATED = 'terminated';
}
