<?php

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Mail\UserInviteMail;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserInviteService
{
    /**
     * @param  list<int>  $callingListIds
     * @return array{user: User, password: string}
     */
    public function invite(
        Company $company,
        string $name,
        string $email,
        UserRole $role,
        array $callingListIds = [],
        bool $sendEmail = true,
        ?string $salesforceId = null,
    ): array {
        $email = strtolower(trim($email));
        $password = Str::password(12);
        $salesforceId = filled($salesforceId) ? trim($salesforceId) : null;

        $user = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('email', $email)
            ->first();

        if ($user) {
            $user->fill([
                'name' => $name,
                'role' => $role,
                'active' => true,
                'password' => $password,
                'must_change_password' => true,
                'email_verified_at' => null,
            ]);

            if ($salesforceId !== null) {
                $user->salesforce_id = $salesforceId;
            }

            $user->save();
        } else {
            $user = new User([
                'company_id' => $company->id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'active' => true,
                'password' => $password,
                'must_change_password' => true,
                'salesforce_id' => $salesforceId,
                'email_verified_at' => null,
            ]);
            $user->save();
        }

        foreach ($callingListIds as $listId) {
            $list = CallingList::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereKey($listId)
                ->first();

            if (! $list) {
                throw new InvalidArgumentException("Calling list {$listId} not found for this company.");
            }

            ListAssignment::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'calling_list_id' => $list->id,
                ],
                [],
            );
        }

        if ($sendEmail) {
            Mail::to($user->email)->send(new UserInviteMail($user, $password));
        }

        return ['user' => $user->refresh(), 'password' => $password];
    }

    public function resend(User $user): string
    {
        $password = Str::password(12);
        $user->password = $password;
        $user->must_change_password = true;
        $user->active = true;
        $user->email_verified_at = null;
        $user->save();

        Mail::to($user->email)->send(new UserInviteMail($user, $password));

        return $password;
    }
}
