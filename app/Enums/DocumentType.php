<?php

namespace App\Enums;

enum DocumentType: string
{
    case IDENTITY_CARD = 'identity_card';
    case BUSINESS_LICENSE = 'business_license';
    case VENUE_PROFILE = 'venue_profile';
    case OTHER = 'other';
}
