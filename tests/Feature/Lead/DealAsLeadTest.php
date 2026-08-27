<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    test()->withoutMiddleware(ValidateCsrfToken::class);
});

function makeKf1Admin(): User
{
    $role = Role::create([
        'name' => 'KF1 Admin Role '.bin2hex(random_bytes(4)),
        'description' => 'Created by the KF1 deal-as-lead tests.',
        'permission_type' => 'all',
    ]);

    return User::create([
        'name' => 'KF1 Admin',
        'email' => 'kf1-admin-'.bin2hex(random_bytes(4)).'@example.invalid',
        'password' => bcrypt('correct-horse-battery-staple'),
        'status' => 1,
        'role_id' => $role->id,
        'view_permission' => 'global',
    ]);
}

function ensureKf1Pipeline(): Pipeline
{
    $pipeline = Pipeline::query()->where('is_default', 1)->first();

    if (! $pipeline) {
        $pipeline = Pipeline::query()->create([
            'name' => 'Default Pipeline',
            'is_default' => 1,
            'rotten_days' => 30,
        ]);
    }

    foreach (Lead::pipelineStages() as $stage) {
        Stage::query()->firstOrCreate(
            [
                'code' => $stage['code'],
                'lead_pipeline_id' => $pipeline->id,
            ],
            [
                'name' => $stage['name'],
                'probability' => $stage['probability'],
                'sort_order' => $stage['sort_order'],
            ]
        );
    }

    return $pipeline->fresh(['stages']);
}

function ensureKf1LeadLookups(): array
{
    $source = Source::query()->first() ?: Source::query()->create(['name' => 'Direct']);
    $type = Type::query()->first() ?: Type::query()->create(['name' => 'New Business']);

    return [$source, $type];
}

it('creates a lead in New application and assigns a rep', function () {
    $admin = makeKf1Admin();
    $pipeline = ensureKf1Pipeline();
    [$source, $type] = ensureKf1LeadLookups();

    $rep = User::create([
        'name' => 'Assigned Rep',
        'email' => 'kf1-rep-'.bin2hex(random_bytes(4)).'@example.invalid',
        'password' => bcrypt('correct-horse-battery-staple'),
        'status' => 1,
        'role_id' => $admin->role_id,
        'view_permission' => 'global',
    ]);

    $response = test()->actingAs($admin, 'user')
        ->post(route('admin.leads.store'), [
            'title' => 'New application lead',
            'user_id' => $rep->id,
            'lead_source_id' => $source->id,
            'lead_type_id' => $type->id,
            'person' => [
                'name' => 'Jamie Contact',
                'emails' => [
                    ['value' => 'jamie-'.bin2hex(random_bytes(3)).'@example.invalid', 'label' => 'work'],
                ],
            ],
        ]);

    $response->assertRedirect();

    $lead = Lead::query()->where('title', 'New application lead')->first();

    expect($lead)->not->toBeNull();
    expect($lead->user_id)->toBe($rep->id);
    expect($lead->stage->code)->toBe('NEW_APPLICATION');
    expect($lead->stage->name)->toBe('New application');
    expect($lead->lead_pipeline_id)->toBe($pipeline->id);
});

it('attaches a PDF tagged bank_statements when creating a lead', function () {
    Storage::fake();

    $admin = makeKf1Admin();
    ensureKf1Pipeline();
    [$source, $type] = ensureKf1LeadLookups();

    $pdf = UploadedFile::fake()->create('bank-statements.pdf', 120, 'application/pdf');

    $response = test()->actingAs($admin, 'user')
        ->post(route('admin.leads.store'), [
            'title' => 'Lead with bank statements',
            'user_id' => $admin->id,
            'lead_source_id' => $source->id,
            'lead_type_id' => $type->id,
            'person' => [
                'name' => 'Alex Contact',
                'emails' => [
                    ['value' => 'alex-'.bin2hex(random_bytes(3)).'@example.invalid', 'label' => 'work'],
                ],
            ],
            'attachments' => [
                [
                    'file' => $pdf,
                    'bucket' => 'bank_statements',
                ],
            ],
        ]);

    $response->assertRedirect();

    $lead = Lead::query()->where('title', 'Lead with bank statements')->first();

    expect($lead)->not->toBeNull();

    $file = ActivityFile::query()
        ->whereIn('activity_id', $lead->activities()->pluck('activities.id'))
        ->first();

    expect($file)->not->toBeNull();
    expect($file->bucket)->toBe('bank_statements');
    expect($file->name)->toBe('bank-statements.pdf');
});

it('attaches a PDF tagged bank_statements when viewing a lead', function () {
    Storage::fake();

    $admin = makeKf1Admin();
    $pipeline = ensureKf1Pipeline();
    [$source, $type] = ensureKf1LeadLookups();

    $stage = $pipeline->stages->firstWhere('code', Lead::DEFAULT_STAGE_CODE);

    $person = Person::query()->create([
        'name' => 'View Contact',
        'emails' => [['value' => 'view-'.bin2hex(random_bytes(3)).'@example.invalid', 'label' => 'work']],
        'user_id' => $admin->id,
        'unique_id' => 'view-'.bin2hex(random_bytes(4)),
    ]);

    $lead = Lead::query()->create([
        'title' => 'Existing lead for file tag',
        'status' => 1,
        'user_id' => $admin->id,
        'person_id' => $person->id,
        'lead_source_id' => $source->id,
        'lead_type_id' => $type->id,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    $pdf = UploadedFile::fake()->create('statements.pdf', 80, 'application/pdf');

    $response = test()->actingAs($admin, 'user')
        ->postJson(route('admin.activities.store'), [
            'type' => 'file',
            'lead_id' => $lead->id,
            'title' => 'Bank statements',
            'file' => $pdf,
            'bucket' => 'bank_statements',
        ]);

    $response->assertSuccessful();

    $file = ActivityFile::query()
        ->whereIn('activity_id', $lead->activities()->pluck('activities.id'))
        ->first();

    expect($file)->not->toBeNull();
    expect($file->bucket)->toBe('bank_statements');
});

it('does not have a deals table', function () {
    expect(Schema::hasTable('deals'))->toBeFalse();
});
