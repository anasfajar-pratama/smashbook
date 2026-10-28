<?php

namespace App\Console\Commands;

use App\Models\MemberSubscription;
use Illuminate\Console\Command;

class CheckMembershipExpiry extends Command
{
    protected $signature = 'smashbook:check-membership';

    protected $description = 'Tandai membership yang sudah kedaluwarsa';

    public function handle(): int
    {
        $expired = MemberSubscription::query()
            ->where('status', 'aktif')
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            $member = $subscription->member;
            if ($member && ! $member->subscriptions()->where('status', 'aktif')->whereDate('end_date', '>=', now()->toDateString())->exists()) {
                $member->update(['status' => 'expired']);
            }
        }

        $this->info("{$expired->count()} membership ditandai expired.");

        return self::SUCCESS;
    }
}
