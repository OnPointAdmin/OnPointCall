<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\CallingList;
use App\Models\Company;
use App\Services\Users\UserInviteService;
use App\Support\CompanyContext;
use Illuminate\Console\Command;
use InvalidArgumentException;
use SplFileObject;

class ImportAgentsCommand extends Command
{
    private const MANAGER_EMAIL = 'heavygrindmedia@gmail.com';

    protected $signature = 'users:import-agents
        {file : Path to agents CSV file}
        {--company= : Company ID}
        {--send-email : Send invite emails (default: no email)}';

    protected $description = 'Import agents from a migration CSV (name, email, lists, Salesforce Id)';

    public function handle(UserInviteService $invites): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $company = $this->resolveCompany();
        CompanyContext::set($company->id);

        $lists = CallingList::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('name', ['Standard', 'TNB'])
            ->pluck('id', 'name');

        if (! isset($lists['Standard'])) {
            $this->error('Standard calling list not found.');

            return self::FAILURE;
        }

        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = array_map(
            fn (mixed $header): string => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $header))),
            $file->fgetcsv() ?: [],
        );

        $imported = 0;
        $passwords = [];

        try {
            while (! $file->eof()) {
                $row = $file->fgetcsv();

                if (! is_array($row) || $this->isBlankRow($row)) {
                    continue;
                }

                $data = $this->rowToAssoc($headers, $row);
                $firstName = trim($data['first name'] ?? '');
                $lastName = trim($data['last name'] ?? '');
                $email = strtolower(trim($data['email'] ?? ''));
                $active = filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOL);
                $tnb = filter_var($data['tnb'] ?? false, FILTER_VALIDATE_BOOL);
                $salesforceId = trim($data['salesforce id'] ?? '');

                if ($email === '') {
                    $this->warn('Skipping row with blank email.');

                    continue;
                }

                if ($salesforceId === '') {
                    $this->warn("No Salesforce Id for {$email}.");
                }

                $name = trim("{$firstName} {$lastName}");
                $role = $email === self::MANAGER_EMAIL ? UserRole::Manager : UserRole::Agent;

                $listIds = [(int) $lists['Standard']];

                if ($tnb && isset($lists['TNB'])) {
                    $listIds[] = (int) $lists['TNB'];
                }

                if (! $active) {
                    $this->warn("Skipping inactive user {$email}.");

                    continue;
                }

                try {
                    $result = $invites->invite(
                        $company,
                        $name,
                        $email,
                        $role,
                        $listIds,
                        sendEmail: (bool) $this->option('send-email'),
                        salesforceId: $salesforceId !== '' ? $salesforceId : null,
                    );
                } catch (InvalidArgumentException $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }

                $imported++;
                $passwords[] = [
                    'name' => $name,
                    'email' => $email,
                    'role' => $role->value,
                    'user_id' => $result['user']->id,
                    'password' => $result['password'],
                    'salesforce_id' => $result['user']->salesforce_id,
                ];

                $this->line("Imported {$name} ({$email}) as {$role->value} [id {$result['user']->id}]");
            }
        } finally {
            CompanyContext::clear();
        }

        if ($passwords !== [] && ! $this->option('send-email')) {
            $this->newLine();
            $this->warn('Temporary passwords (email not sent):');
            $this->table(
                ['Name', 'Email', 'Role', 'User ID', 'Password', 'Salesforce Id'],
                array_map(
                    fn (array $row): array => [
                        $row['name'],
                        $row['email'],
                        $row['role'],
                        $row['user_id'],
                        $row['password'],
                        $row['salesforce_id'] ?? '',
                    ],
                    $passwords,
                ),
            );
        }

        $this->info("Imported {$imported} user(s).");

        return self::SUCCESS;
    }

    private function resolveCompany(): Company
    {
        if ($companyId = $this->option('company')) {
            return Company::query()->findOrFail($companyId);
        }

        return Company::query()->firstOrFail();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    private function rowToAssoc(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
