<?php

namespace Webkul\Funder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Funder\Contracts\Funder as FunderContract;

/**
 * Destination a lead can be submitted to.
 *
 * `criteria` is stored as JSON and is not matched in KF2. Matching is K51/K52.
 *
 * Eligibility fields (store as-is; blank criteria is valid):
 * - min_monthly_revenue
 * - max_monthly_revenue
 * - min_fico
 * - max_fico
 * - allowed_states
 * - restricted_states
 * - min_time_in_business_months
 * - min_requested_amount
 * - max_requested_amount
 * - existing_positions
 * - nsf_max
 * - industry_exclude
 * - naics_exclude
 * - bankruptcy
 * - defaults
 * - min_adb
 * - entity_types
 * - max_term
 * - min_factor
 * - max_factor
 * - use_of_funds
 * - max_existing_positions
 * - min_avg_daily_balance
 *
 * @property array<string, mixed>|null $criteria
 */
class Funder extends Model implements FunderContract
{
    /**
     * Supported destination kinds. Only `sandbox` is implemented in KF2.
     */
    public const KINDS = [
        'sandbox',
        'email',
        'api',
        'webhook',
    ];

    /**
     * Eligibility keys stored on `criteria` (JSON). Matching is K51/K52.
     */
    public const CRITERIA_FIELDS = [
        'min_monthly_revenue',
        'max_monthly_revenue',
        'min_fico',
        'max_fico',
        'allowed_states',
        'restricted_states',
        'min_time_in_business_months',
        'min_requested_amount',
        'max_requested_amount',
        'existing_positions',
        'nsf_max',
        'industry_exclude',
        'naics_exclude',
        'bankruptcy',
        'defaults',
        'min_adb',
        'entity_types',
        'max_term',
        'min_factor',
        'max_factor',
        'use_of_funds',
        'max_existing_positions',
        'min_avg_daily_balance',
    ];

    protected $table = 'funders';

    /**
     * The attributes that are mass assignable.
     *
     * `portal_task` exists on the table and is unused in KF2.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'kind',
        'route',
        'criteria',
        'portal_task',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'criteria' => 'array',
    ];

    /**
     * Submissions sent to this funder.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(SubmissionProxy::modelClass());
    }
}
