<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Webkul\Contact\Models\Person;
use Webkul\Funder\Models\Funder;
use Webkul\Funder\Models\Submission;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\Quote\Models\Quote;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    test()->withoutMiddleware(ValidateCsrfToken::class);
});

function makeKf2Admin(): User
{
    $role = Role::create([
        'name' => 'KF2 Admin Role '.bin2hex(random_bytes(4)),
        'description' => 'Created by the KF2 funder tests.',
        'permission_type' => 'all',
    ]);

    return User::create([
        'name' => 'KF2 Admin',
        'email' => 'kf2-admin-'.bin2hex(random_bytes(4)).'@example.invalid',
        'password' => bcrypt('correct-horse-battery-staple'),
        'status' => 1,
        'role_id' => $role->id,
        'view_permission' => 'global',
    ]);
}

function ensureKf2Pipeline(): Pipeline
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

function ensureKf2LeadLookups(): array
{
    $source = Source::query()->first() ?: Source::query()->create(['name' => 'Direct']);
    $type = Type::query()->first() ?: Type::query()->create(['name' => 'New Business']);

    return [$source, $type];
}

function makeKf2Lead(User $admin): Lead
{
    $pipeline = ensureKf2Pipeline();
    [$source, $type] = ensureKf2LeadLookups();

    $stage = $pipeline->stages->firstWhere('code', Lead::DEFAULT_STAGE_CODE);

    $person = Person::query()->create([
        'name' => 'KF2 Contact',
        'emails' => [['value' => 'kf2-'.bin2hex(random_bytes(3)).'@example.invalid', 'label' => 'work']],
        'user_id' => $admin->id,
        'unique_id' => 'kf2-'.bin2hex(random_bytes(4)),
    ]);

    return Lead::query()->create([
        'title' => 'KF2 lead '.bin2hex(random_bytes(4)),
        'status' => 1,
        'user_id' => $admin->id,
        'person_id' => $person->id,
        'lead_source_id' => $source->id,
        'lead_type_id' => $type->id,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);
}

function kf2CriteriaPayload(): array
{
    return [
        'min_monthly_revenue' => 10000,
        'max_monthly_revenue' => 500000,
        'min_fico' => 500,
        'max_fico' => 850,
        'allowed_states' => ['NY', 'NJ'],
        'restricted_states' => ['NV'],
        'min_time_in_business_months' => 6,
        'min_requested_amount' => 5000,
        'max_requested_amount' => 250000,
        'existing_positions' => 1,
        'nsf_max' => 3,
        'industry_exclude' => ['gambling'],
        'naics_exclude' => ['7132'],
        'bankruptcy' => false,
        'defaults' => false,
        'min_adb' => 2500,
        'entity_types' => ['llc', 'corp'],
        'max_term' => 18,
        'min_factor' => 1.1,
        'max_factor' => 1.5,
        'use_of_funds' => ['working_capital'],
        'max_existing_positions' => 2,
        'min_avg_daily_balance' => 2500,
    ];
}

it('shows the funders settings index', function () {
    $admin = makeKf2Admin();

    test()->actingAs($admin, 'user')
        ->get(route('admin.settings.funders.index'))
        ->assertOk()
        ->assertSee('Funders');
});

it('creates a sandbox funder with blank criteria', function () {
    $admin = makeKf2Admin();

    test()->actingAs($admin, 'user')
        ->post(route('admin.settings.funders.store'), [
            'name' => 'Sandbox Funder',
            'kind' => 'sandbox',
            'criteria' => null,
        ])
        ->assertValid();

    $funder = Funder::query()->where('name', 'Sandbox Funder')->first();

    expect($funder)->not->toBeNull();
    expect($funder->kind)->toBe('sandbox');
    expect($funder->criteria === null || $funder->criteria === [])->toBeTrue();
});

it('creates a funder with the full eligibility criteria keys', function () {
    $admin = makeKf2Admin();
    $criteria = kf2CriteriaPayload();

    expect(count($criteria))->toBeGreaterThanOrEqual(20);
    expect(array_keys($criteria))->toEqual(Funder::CRITERIA_FIELDS);

    test()->actingAs($admin, 'user')
        ->postJson(route('admin.settings.funders.store'), [
            'name' => 'Criteria Funder',
            'kind' => 'email',
            'route' => 'funding@example.invalid',
            'criteria' => $criteria,
        ])
        ->assertOk()
        ->assertValid();

    $funder = Funder::query()->where('name', 'Criteria Funder')->first();

    expect($funder)->not->toBeNull();
    expect($funder->criteria)->toMatchArray($criteria);
});

it('updates a funder', function () {
    $admin = makeKf2Admin();

    $funder = Funder::query()->create([
        'name' => 'Original Funder',
        'kind' => 'sandbox',
    ]);

    test()->actingAs($admin, 'user')
        ->put(route('admin.settings.funders.update', $funder->id), [
            'name' => 'Updated Funder',
            'kind' => 'webhook',
            'route' => 'https://example.invalid/hook',
        ])
        ->assertValid();

    $funder->refresh();

    expect($funder->name)->toBe('Updated Funder');
    expect($funder->kind)->toBe('webhook');
    expect($funder->route)->toBe('https://example.invalid/hook');
});

it('deletes a funder', function () {
    $admin = makeKf2Admin();

    $funder = Funder::query()->create([
        'name' => 'Disposable Funder',
        'kind' => 'sandbox',
    ]);

    test()->actingAs($admin, 'user')
        ->delete(route('admin.settings.funders.delete', $funder->id));

    expect(Funder::query()->find($funder->id))->toBeNull();
});

it('rejects an invalid funder kind', function () {
    $admin = makeKf2Admin();

    test()->actingAs($admin, 'user')
        ->from(route('admin.settings.funders.index'))
        ->post(route('admin.settings.funders.store'), [
            'name' => 'Invalid Kind Funder',
            'kind' => 'fax',
        ])
        ->assertInvalid(['kind']);

    expect(Funder::query()->where('name', 'Invalid Kind Funder')->exists())->toBeFalse();
});

it('submits a lead to a sandbox funder and writes a fixture quote', function () {
    Mail::fake();

    $admin = makeKf2Admin();
    $lead = makeKf2Lead($admin);

    $funder = Funder::query()->create([
        'name' => 'Sandbox Funder',
        'kind' => 'sandbox',
        'route' => 'sandbox://local',
    ]);

    test()->actingAs($admin, 'user')
        ->postJson(route('admin.leads.funders.submit', $lead->id), [
            'funder_ids' => [$funder->id],
        ])
        ->assertOk();

    $submission = Submission::query()->where('lead_id', $lead->id)->first();

    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe(Submission::STATUS_SENT);
    expect($submission->funder_id)->toBe($funder->id);

    $quote = Quote::query()
        ->where('subject', 'Sandbox offer from '.$funder->name)
        ->first();

    expect($quote)->not->toBeNull();
    expect($quote->person_id)->toBe($lead->person_id);
    expect($quote->user_id)->toBe($lead->user_id);
    expect($lead->quotes()->where('quotes.id', $quote->id)->exists())->toBeTrue();
    expect(Schema::hasTable('offers'))->toBeFalse();

    Mail::assertNothingSent();
});

it('keeps a sandbox submit independent of an unimplemented api funder', function () {
    $admin = makeKf2Admin();
    $lead = makeKf2Lead($admin);

    $sandbox = Funder::query()->create([
        'name' => 'Sandbox Funder',
        'kind' => 'sandbox',
    ]);

    $api = Funder::query()->create([
        'name' => 'API Funder',
        'kind' => 'api',
        'route' => 'https://example.invalid/api',
    ]);

    test()->actingAs($admin, 'user')
        ->postJson(route('admin.leads.funders.submit', $lead->id), [
            'funder_ids' => [$sandbox->id, $api->id],
        ])
        ->assertOk();

    $sandboxSubmission = Submission::query()
        ->where('lead_id', $lead->id)
        ->where('funder_id', $sandbox->id)
        ->first();

    $apiSubmission = Submission::query()
        ->where('lead_id', $lead->id)
        ->where('funder_id', $api->id)
        ->first();

    expect($sandboxSubmission)->not->toBeNull();
    expect($sandboxSubmission->status)->toBe(Submission::STATUS_SENT);
    expect($apiSubmission)->not->toBeNull();
    expect($apiSubmission->status)->toBe(Submission::STATUS_ERRORED);
    expect($apiSubmission->error_message)->toContain('not implemented');

    expect($lead->quotes()->count())->toBe(1);
    expect($lead->quotes()->first()->subject)->toBe('Sandbox offer from '.$sandbox->name);
});

it('has an unused portal_task column on funders', function () {
    expect(Schema::hasColumn('funders', 'portal_task'))->toBeTrue();
});

it('does not register a portal-login route', function () {
    $routes = collect(Route::getRoutes())->map(function ($route) {
        return strtolower(($route->getName() ?? '').' '.$route->uri());
    })->implode("\n");

    expect($routes)->not->toContain('portal-login');
    expect($routes)->not->toContain('portal_login');
});
