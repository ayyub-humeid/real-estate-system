<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renamePermission('submit_offer_revision', 'finalize_offer_revision');
    }

    public function down(): void
    {
        $this->renamePermission('finalize_offer_revision', 'submit_offer_revision');
    }

    private function renamePermission(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to): void {
            $old = DB::table('permissions')->where('name', $from)->where('guard_name', 'web')->first();

            if (! $old) {
                return;
            }

            $new = DB::table('permissions')->where('name', $to)->where('guard_name', 'web')->first();

            if (! $new) {
                DB::table('permissions')->where('id', $old->id)->update(['name' => $to]);
                return;
            }

            foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
                foreach (DB::table($table)->where('permission_id', $old->id)->get() as $pivot) {
                    $attributes = (array) $pivot;
                    $attributes['permission_id'] = $new->id;
                    DB::table($table)->insertOrIgnore($attributes);
                }

                DB::table($table)->where('permission_id', $old->id)->delete();
            }

            DB::table('permissions')->where('id', $old->id)->delete();
        });
    }
};
