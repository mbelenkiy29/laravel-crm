<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;

class PipelineSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('lead_pipelines')->delete();

        DB::table('lead_pipeline_stages')->delete();

        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        DB::table('lead_pipelines')->insert([
            [
                'id' => 1,
                'name' => trans('installer::app.seeders.lead.pipeline.default', [], $defaultLocale),
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stages = [];

        foreach (Lead::pipelineStages() as $index => $stage) {
            $translationKey = 'installer::app.seeders.lead.pipeline.pipeline-stages.'.str_replace('_', '-', strtolower($stage['code']));

            $name = trans($translationKey, [], $defaultLocale);

            if ($name === $translationKey) {
                $name = $stage['name'];
            }

            $stages[] = [
                'id' => $index + 1,
                'code' => $stage['code'],
                'name' => $name,
                'probability' => $stage['probability'],
                'sort_order' => $stage['sort_order'],
                'lead_pipeline_id' => 1,
            ];
        }

        DB::table('lead_pipeline_stages')->insert($stages);
    }
}
