<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\CallingList;
use App\Models\Company;
use App\Services\Users\UserInviteService;
use App\Support\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InviteUserCommand extends Command
{
    protected $signature = 'user:invite
                            {email : Invitee email address}
                            {--name= : Display name}
                            {--role=agent : admin, manager, or agent}
                            {--lists=Standard : Comma-separated calling list names}
                            {--salesforce-id= : Salesforce user ID}
                            {--company= : Company ID (defaults to first company)}
                            {--no-email : Create the account without sending mail}';

    protected $description = 'Create or update a user, assign calling lists, and email a temporary password';

    public function handle(UserInviteService $invites): int
    {
        $company = $this->option('company')
            ? Company::query()->find($this->option('company'))
            : Company::query()->first();

        if (! $company) {
            $this->error('No company found.');

            return self::FAILURE;
        }

        $role = UserRole::tryFrom((string) $this->option('role'));

        if (! $role) {
            $this->error('Role must be admin, manager, or agent.');

            return self::FAILURE;
        }

        $listNames = collect(explode(',', (string) $this->option('lists')))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values();

        CompanyContext::set($company->id);

        try {
            $listIds = CallingList::query()
                ->where('company_id', $company->id)
                ->whereIn('name', $listNames)
                ->pluck('id')
                ->all();

            if (count($listIds) !== $listNames->count()) {
                $this->error('One or more calling lists were not found: '.$listNames->implode(', '));

                return self::FAILURE;
            }

            $email = (string) $this->argument('email');
            $name = (string) ($this->option('name') ?: Str::of($email)->before('@')->replace(['.', '_', '-'], ' ')->title());

            $result = $invites->invite(
                $company,
                $name,
                $email,
                $role,
                $listIds,
                sendEmail: ! $this->option('no-email'),
                salesforceId: $this->option('salesforce-id') ?: null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            CompanyContext::clear();
        }

        $this->info("Invited {$result['user']->email} as {$role->value}.");
        $this->line("Temporary password: {$result['password']}");
        $this->line('Sign in: '.url('/'));

        if ($this->option('no-email')) {
            $this->warn('Email was not sent (--no-email).');
        }

        return self::SUCCESS;
    }
}
