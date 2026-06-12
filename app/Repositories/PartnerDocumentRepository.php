<?php

namespace App\Repositories;

use App\Models\PartnerDocument;

class PartnerDocumentRepository
{
    public function create(array $data): PartnerDocument
    {
        return PartnerDocument::create($data);
    }

    public function createMany(array $documents): bool
    {
        return PartnerDocument::insert($documents);
    }
}
