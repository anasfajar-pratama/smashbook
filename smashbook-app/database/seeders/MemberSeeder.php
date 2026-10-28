<?php

namespace Database\Seeders;

use App\Models\Deposit;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\MemberSubscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $kasir = User::query()->where('email', 'dewi@smasharena.id')->first();
        $types = MembershipType::query()->get()->keyBy('name');

        $members = [
            [
                'code' => 'MBR-00003', 'name' => 'Budi Santoso', 'phone' => '0812-3344-5566', 'email' => 'budi.s@mail.com',
                'gender' => 'Laki-laki', 'type' => 'Member Bulanan', 'start_offset' => -20, 'end_offset' => 10,
                'deposit' => 250000, 'status' => 'aktif', 'no_show' => 0, 'join_offset' => -420,
            ],
            [
                'code' => 'MBR-00012', 'name' => 'Rina Wijaya', 'phone' => '0813-9988-1122', 'email' => 'rina@mail.com',
                'gender' => 'Perempuan', 'type' => 'Member Bulanan', 'start_offset' => -5, 'end_offset' => 25,
                'deposit' => 0, 'status' => 'aktif', 'no_show' => 0, 'join_offset' => -90,
            ],
            [
                'code' => 'MBR-00031', 'name' => 'Komunitas Smash', 'phone' => '0811-2233-8899', 'email' => 'admin@smashcommunity.id',
                'gender' => null, 'type' => 'Komunitas / Korporat', 'start_offset' => -30, 'end_offset' => 30,
                'deposit' => 1200000, 'status' => 'aktif', 'no_show' => 0, 'join_offset' => -300,
            ],
            [
                'code' => 'MBR-00044', 'name' => 'Siti Nurhaliza', 'phone' => '0857-4455-6677', 'email' => 'siti@mail.com',
                'gender' => 'Perempuan', 'type' => 'Member Bulanan', 'start_offset' => -23, 'end_offset' => 7,
                'deposit' => 120000, 'status' => 'aktif', 'no_show' => 0, 'join_offset' => -120,
            ],
            [
                'code' => 'MBR-00050', 'name' => 'Andi Pratama', 'phone' => '0821-7766-5544', 'email' => 'andi.p@mail.com',
                'gender' => 'Laki-laki', 'type' => 'Member 3 Bulan', 'start_offset' => -120, 'end_offset' => -30,
                'deposit' => 0, 'status' => 'expired', 'no_show' => 0, 'join_offset' => -200,
            ],
            [
                'code' => 'MBR-00058', 'name' => 'Ahmad Fauzi', 'phone' => '0821-9988-7766', 'email' => null,
                'gender' => 'Laki-laki', 'type' => 'Member Bulanan', 'start_offset' => -14, 'end_offset' => 16,
                'deposit' => 0, 'status' => 'perlu_konfirmasi', 'no_show' => 3, 'join_offset' => -60,
            ],
        ];

        foreach ($members as $index => $data) {
            $type = $types[$data['type']] ?? null;

            $member = Member::query()->updateOrCreate(['code' => $data['code']], [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'gender' => $data['gender'],
                'member_type_id' => $type?->id,
                'join_date' => now()->addDays($data['join_offset'])->toDateString(),
                'status' => $data['status'],
                'no_show_count' => $data['no_show'],
                'deposit_balance' => $data['deposit'],
            ]);

            $start = now()->addDays($data['start_offset']);
            $end = now()->addDays($data['end_offset']);

            MemberSubscription::query()->updateOrCreate(
                ['member_id' => $member->id, 'start_date' => $start->toDateString()],
                [
                    'membership_type_id' => $type?->id,
                    'end_date' => $end->toDateString(),
                    'price' => $type?->price ?? 0,
                    'paid_amount' => $type?->price ?? 0,
                    'status' => $data['status'] === 'expired' ? 'expired' : 'aktif',
                    'invoice_code' => 'SUB-SEED-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'created_by' => $kasir?->id,
                ]
            );

            if ($data['deposit'] > 0) {
                Deposit::query()->updateOrCreate(
                    ['code' => 'DEP-SEED-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                    [
                        'member_id' => $member->id,
                        'type' => 'topup',
                        'amount' => $data['deposit'],
                        'balance_after' => $data['deposit'],
                        'created_by' => $kasir?->id,
                        'created_at' => now()->subDays(10),
                    ]
                );
            }
        }
    }
}
