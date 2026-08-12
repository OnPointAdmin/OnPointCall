<?php

namespace App\Console\Commands;

use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\PhoneNormalizer;
use App\Support\TimezoneResolver;
use Illuminate\Console\Command;

class SlimLeadMigrationCommand extends Command
{
    protected $signature = 'leads:migrate-slim
        {file : Path to CSV file}
        {--company= : Company ID}
        {--lead-type=standard : Lead type (standard|tnb)}
        {--list= : Optional calling list ID to assign released leads}';

    protected $description = 'One-time slim migration: import leads with last disposition and last owner only';

    public function handle(PhoneNormalizer $phoneNormalizer, TimezoneResolver $timezoneResolver): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $company = $this->resolveCompany();
        CompanyContext::set($company->id);

        $leadType = LeadType::from($this->option('lead-type'));
        $listId = $this->option('list') ? (int) $this->option('list') : null;
        $status = $listId ? LeadStatus::Callable : LeadStatus::Holding;

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);

        if (! $headers) {
            $this->error('Empty CSV.');

            return self::FAILURE;
        }

        $headers = array_map('trim', $headers);
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            if (! $data) {
                continue;
            }

            $phone = $phoneNormalizer->normalize($data['phone'] ?? $data['Phone'] ?? '');

            if (! $phone) {
                $skipped++;

                continue;
            }

            $externalId = $data['external_lead_id'] ?? $data['lead_id'] ?? $data['Lead ID'] ?? null;

            $exists = Lead::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where(function ($query) use ($phone, $externalId): void {
                    $query->where('phone', $phone);

                    if ($externalId) {
                        $query->orWhere('external_lead_id', $externalId);
                    }
                })
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $state = strtoupper(trim($data['state'] ?? $data['State'] ?? ''));
            $zip = trim($data['zip'] ?? $data['Zip'] ?? '');

            $lead = Lead::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'phone' => $phone,
                'first_name' => $data['first_name'] ?? $data['First Name'] ?? null,
                'last_name' => $data['last_name'] ?? $data['Last Name'] ?? null,
                'address' => $data['address'] ?? $data['Address'] ?? null,
                'city' => $data['city'] ?? $data['City'] ?? null,
                'state' => $state,
                'zip' => $zip,
                'email' => $data['email'] ?? $data['Email'] ?? null,
                'venue' => $data['venue'] ?? $data['Venue'] ?? null,
                'event' => $data['event'] ?? $data['Event'] ?? null,
                'external_lead_id' => $externalId,
                'partner_list' => $data['partner_list'] ?? $data['Partner List'] ?? null,
                'file_name' => $data['file_name'] ?? $data['File Name'] ?? null,
                'timezone' => $timezoneResolver->resolve($state, $zip),
                'status' => $status,
                'lead_type' => $leadType,
                'calling_list_id' => $listId,
                'imported_at' => now(),
                'queue_rank' => 0,
            ]);

            $lastDisposition = strtolower(trim($data['last_disposition'] ?? $data['Last Disposition'] ?? ''));
            $lastOwnerEmail = strtolower(trim($data['last_owner'] ?? $data['Last Owner'] ?? ''));

            if ($lastDisposition || $lastOwnerEmail) {
                $owner = $lastOwnerEmail
                    ? User::withoutGlobalScopes()
                        ->where('company_id', $company->id)
                        ->where('email', $lastOwnerEmail)
                        ->first()
                    : null;

                $mappedStatus = $this->mapDispositionToStatus($lastDisposition);

                if ($mappedStatus) {
                    $lead->update(['status' => $mappedStatus]);
                }

                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'actor_id' => $owner?->id,
                    'event_type' => LeadHistoryType::Disposition,
                    'occurred_at' => now(),
                    'payload' => [
                        'disposition' => $lastDisposition ?: 'imported',
                        'source' => 'slim_migration',
                        'last_owner_email' => $lastOwnerEmail ?: null,
                    ],
                ]);
            }

            $imported++;
        }

        fclose($handle);
        CompanyContext::clear();

        $this->info("Imported {$imported} lead(s), skipped {$skipped} duplicate(s).");

        return self::SUCCESS;
    }

    private function resolveCompany(): Company
    {
        if ($companyId = $this->option('company')) {
            return Company::query()->findOrFail($companyId);
        }

        return Company::query()->firstOrFail();
    }

    private function mapDispositionToStatus(string $disposition): ?LeadStatus
    {
        return match ($disposition) {
            'booked' => LeadStatus::Booked,
            'callback' => LeadStatus::Callback,
            'dnc' => LeadStatus::Dnc,
            'not_interested', 'wrong_number', 'bad_lead', 'bad_number', 'terminal' => LeadStatus::Terminal,
            default => null,
        };
    }
}
