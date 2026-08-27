<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add business identity attributes on leads for existing installs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('attributes')) {
            return;
        }

        $now = Carbon::now();
        $locale = config('app.locale');

        $attributes = [
            [
                'code' => 'ein',
                'name' => trans('installer::app.seeders.attributes.leads.ein', [], $locale),
                'type' => 'text',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '11',
            ], [
                'code' => 'dba',
                'name' => trans('installer::app.seeders.attributes.leads.dba', [], $locale),
                'type' => 'text',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '12',
            ], [
                'code' => 'revenue',
                'name' => trans('installer::app.seeders.attributes.leads.revenue', [], $locale),
                'type' => 'price',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '13',
            ], [
                'code' => 'fico',
                'name' => trans('installer::app.seeders.attributes.leads.fico', [], $locale),
                'type' => 'text',
                'lookup_type' => null,
                'validation' => 'numeric',
                'sort_order' => '14',
            ], [
                'code' => 'requested_amount',
                'name' => trans('installer::app.seeders.attributes.leads.requested-amount', [], $locale),
                'type' => 'price',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '15',
            ], [
                'code' => 'default_status',
                'name' => trans('installer::app.seeders.attributes.leads.default-status', [], $locale),
                'type' => 'select',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '16',
            ],
        ];

        foreach ($attributes as $attribute) {
            $exists = DB::table('attributes')
                ->where('code', $attribute['code'])
                ->where('entity_type', 'leads')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('attributes')->insert([
                'code' => $attribute['code'],
                'name' => $attribute['name'],
                'type' => $attribute['type'],
                'entity_type' => 'leads',
                'lookup_type' => $attribute['lookup_type'],
                'validation' => $attribute['validation'],
                'sort_order' => $attribute['sort_order'],
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '0',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('attributes')
            ->where('code', 'user_id')
            ->where('entity_type', 'leads')
            ->update([
                'name' => trans('installer::app.seeders.attributes.leads.sales-owner', [], $locale),
                'updated_at' => $now,
            ]);

        $this->seedDefaultStatusOptions($locale);
    }

    /**
     * Seed default-status options when missing.
     */
    protected function seedDefaultStatusOptions(string $locale): void
    {
        if (! Schema::hasTable('attribute_options')) {
            return;
        }

        $attributeId = DB::table('attributes')
            ->where('code', 'default_status')
            ->where('entity_type', 'leads')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        $options = [
            trans('installer::app.seeders.attributes.leads.default-status-options.clean-file', [], $locale),
            trans('installer::app.seeders.attributes.leads.default-status-options.satisfied-default', [], $locale),
            trans('installer::app.seeders.attributes.leads.default-status-options.open-default', [], $locale),
        ];

        foreach ($options as $index => $name) {
            $exists = DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('attribute_options')->insert([
                'name' => $name,
                'sort_order' => $index + 1,
                'attribute_id' => $attributeId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('attributes')) {
            return;
        }

        DB::table('attributes')
            ->where('entity_type', 'leads')
            ->whereIn('code', ['ein', 'dba', 'revenue', 'fico', 'requested_amount', 'default_status'])
            ->delete();
    }
};
