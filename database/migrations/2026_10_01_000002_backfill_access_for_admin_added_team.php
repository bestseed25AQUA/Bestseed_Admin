<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give real access to people the admin panel added to a farm's team.
 *
 * `managers` is an address book; `farm_access_members` is what the app reads
 * when it decides whether a farmer may open a farm. The admin screen only ever
 * wrote the first, so everyone added there was invisible in the app — to them
 * and to the owner.
 *
 * Only fills gaps: a membership the farmer granted themselves already carries
 * the permissions they chose, and must not be overwritten by the address book.
 */
return new class extends Migration
{
    public function up(): void
    {
        $owners = DB::table('farms')->pluck('farmer_id', 'id');

        $existing = DB::table('farm_access_members')
            ->select('farm_id', 'farmer_id')
            ->get()
            ->map(fn ($row) => $row->farm_id . ':' . $row->farmer_id)
            ->flip();

        $rows = [];
        $now  = now();

        $managers = DB::table('managers')->whereNotNull('farm_id')->get();

        foreach ($managers as $member) {
            $phone = preg_replace('/\D/', '', (string) $member->phone);

            if ($phone === '') {
                continue;
            }

            $farmerId = DB::table('farmers')->where('mobile', $phone)->value('id');
            $ownerId  = $owners[$member->farm_id] ?? null;

            if (!$farmerId || $ownerId === null || (int) $ownerId === (int) $farmerId) {
                continue;
            }

            if ($existing->has($member->farm_id . ':' . $farmerId)) {
                continue;
            }

            $rows[] = [
                'farm_id'            => $member->farm_id,
                'farmer_id'          => $farmerId,
                'manager_id'         => $member->id,
                'granted_by'         => $ownerId,
                'role'               => $member->is_partner ? 'partner' : 'manager',
                'display_name'       => $member->name,
                'view_access'        => (int) $member->view_access,
                'edit_access'        => (int) $member->edit_access,
                'tank_status_access' => (int) $member->tank_status_access,
                'total_feed_access'  => (int) $member->total_feed_access,
                'create_access'      => (int) $member->create_access,
                'delete_access'      => (int) $member->delete_access,
                'expires_at'         => null,
                'revoked_at'         => null,
                'created_at'         => $member->created_at ?? $now,
                'updated_at'         => $now,
            ];

            $existing->put($member->farm_id . ':' . $farmerId, true);
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('farm_access_members')->insert($chunk);
        }
    }

    /**
     * Deliberately nothing. The rows this adds are indistinguishable from
     * those the QR backfill wrote, so removing them by shape would take real
     * access away from people who never came through the admin panel.
     */
    public function down(): void
    {
    }
};
