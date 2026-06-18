<?php

namespace App\Repositories;

use App\Models\PartnerContract;

class PartnerContractRepository
{
    public function findById(string $id): ?PartnerContract
    {
        return PartnerContract::with(['application', 'template', 'signatures'])->find($id);
    }

    public function create(array $data): PartnerContract
    {
        return PartnerContract::create($data);
    }

    public function update(PartnerContract $contract, array $data): bool
    {
        return $contract->update($data);
    }
}
