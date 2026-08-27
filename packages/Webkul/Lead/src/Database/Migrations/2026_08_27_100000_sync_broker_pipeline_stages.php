<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Models\Lead;

return new class extends Migration
{
    /**
     * Replace or extend the default pipeline's stages with the documented broker enum.
     * Existing leads are remapped onto the new codes. No deals table is created.
     */
    public function up(): void
    {
        if (! Schema::hasTable('lead_pipelines') || ! Schema::hasTable('lead_pipeline_stages')) {
            return;
        }

        $pipeline = DB::table('lead_pipelines')->where('is_default', 1)->first()
            ?? DB::table('lead_pipelines')->orderBy('id')->first();

        if (! $pipeline) {
            return;
        }

        $documented = Lead::pipelineStages();
        $documentedCodes = array_column($documented, 'code');

        $existing = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipeline->id)
            ->get()
            ->keyBy('code');

        foreach ($documented as $stage) {
            if ($existing->has($stage['code'])) {
                DB::table('lead_pipeline_stages')
                    ->where('id', $existing[$stage['code']]->id)
                    ->update([
                        'name' => $stage['name'],
                        'probability' => $stage['probability'],
                        'sort_order' => $stage['sort_order'],
                    ]);

                continue;
            }

            DB::table('lead_pipeline_stages')->insert([
                'code' => $stage['code'],
                'name' => $stage['name'],
                'probability' => $stage['probability'],
                'sort_order' => $stage['sort_order'],
                'lead_pipeline_id' => $pipeline->id,
            ]);
        }

        $stagesByCode = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipeline->id)
            ->get()
            ->keyBy('code');

        $leads = DB::table('leads')
            ->where('lead_pipeline_id', $pipeline->id)
            ->get(['id', 'lead_pipeline_stage_id']);

        $stagesById = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipeline->id)
            ->get()
            ->keyBy('id');

        foreach ($leads as $lead) {
            $current = $stagesById->get($lead->lead_pipeline_stage_id);
            $mappedCode = Lead::mapLegacyStageCode($current->code ?? null);
            $target = $stagesByCode->get($mappedCode) ?? $stagesByCode->get(Lead::DEFAULT_STAGE_CODE);

            if (! $target || $target->id === $lead->lead_pipeline_stage_id) {
                continue;
            }

            DB::table('leads')
                ->where('id', $lead->id)
                ->update(['lead_pipeline_stage_id' => $target->id]);
        }

        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipeline->id)
            ->whereNotIn('code', $documentedCodes)
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
