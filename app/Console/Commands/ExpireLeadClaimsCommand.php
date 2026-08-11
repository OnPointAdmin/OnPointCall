<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Leads\LeadClaimService;
use Illuminate\Console\Command;

class ExpireLeadClaimsCommand extends Command
{
    protected $signature = 'claims:expire';

    protected $description = 'Expire stale lead claims and return leads to the shared pool';

    public function handle(LeadClaimService $claimService): int
    {
        $total = 0;

        Company::query()->where('active', true)->pluck('id')->each(
            function (int $companyId) use ($claimService, &$total): void {
                $total += $claimService->expireStaleClaims($companyId);
            },
        );

        $this->info("Expired {$total} stale claim(s).");

        return self::SUCCESS;
    }
}
