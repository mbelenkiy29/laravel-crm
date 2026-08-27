<?php

namespace Webkul\Lead\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Email\Models\EmailProxy;
use Webkul\Lead\Contracts\Lead as LeadContract;
use Webkul\Quote\Models\QuoteProxy;
use Webkul\Tag\Models\TagProxy;
use Webkul\User\Models\UserProxy;

class Lead extends Model implements LeadContract
{
    use CustomAttribute, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'lead_value',
        'status',
        'lost_reason',
        'expected_close_date',
        'closed_at',
        'user_id',
        'person_id',
        'lead_source_id',
        'lead_type_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
    ];

    /**
     * Cast the attributes to their respective types.
     *
     * @var array
     */
    protected $casts = [
        'closed_at' => 'datetime:D M d, Y H:i A',
        'expected_close_date' => 'date:D M d, Y',
    ];

    /**
     * The attributes that are appended.
     *
     * @var array
     */
    protected $appends = [
        'rotten_days',
    ];

    /**
     * Get the user that owns the lead.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get the person that owns the lead.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    /**
     * Get the type that owns the lead.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeProxy::modelClass(), 'lead_type_id');
    }

    /**
     * Get the source that owns the lead.
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceProxy::modelClass(), 'lead_source_id');
    }

    /**
     * Get the pipeline that owns the lead.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(PipelineProxy::modelClass(), 'lead_pipeline_id');
    }

    /**
     * Get the pipeline stage that owns the lead.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(StageProxy::modelClass(), 'lead_pipeline_stage_id');
    }

    /**
     * Get the activities.
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(ActivityProxy::modelClass(), 'lead_activities');
    }

    /**
     * Get the products.
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductProxy::modelClass());
    }

    /**
     * Get the emails.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(EmailProxy::modelClass());
    }

    /**
     * The quotes that belong to the lead.
     */
    public function quotes(): BelongsToMany
    {
        return $this->belongsToMany(QuoteProxy::modelClass(), 'lead_quotes');
    }

    /**
     * The tags that belong to the lead.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'lead_tags');
    }

    /**
     * Default pipeline stage for a newly created lead.
     */
    public const DEFAULT_STAGE_CODE = 'NEW_APPLICATION';

    /**
     * Lead identity attribute codes stored as custom attributes.
     */
    public const IDENTITY_ATTRIBUTE_CODES = [
        'ein',
        'dba',
        'revenue',
        'fico',
        'requested_amount',
        'default_status',
    ];

    /**
     * Documented broker pipeline stages plus kanban group metadata.
     *
     * Later tickets should call this method instead of duplicating the enum.
     */
    public static function pipelineStages(): array
    {
        $groupLabels = [
            'lead' => 'Lead',
            'new' => 'New',
            'submitted' => 'Submitted',
            'offers' => 'Offers',
            'contracts' => 'Contracts',
            'funded' => 'Funded',
            'closed' => 'Closed',
        ];

        $groupStageOrder = [
            'lead' => ['LEAD'],
            'new' => ['NEW_APPLICATION', 'MISSING_DOCUMENTS', 'READY_TO_SUBMIT'],
            'submitted' => ['SUBMITTED', 'RECEIVED_DLVC', 'RESUBMITTING'],
            'offers' => ['APPROVED', 'OFFER_SELECTED', 'OFFER_PITCHED', 'OFFER_ACCEPTED', 'REPRICING', 'FINAL_REVIEW'],
            'contracts' => ['CONTRACTS_REQUESTED', 'CONTRACTS_SENT', 'CONTRACTS_SIGNED'],
            'funded' => ['FUNDED', 'FUNDED_UP_FOR_RENEWAL', 'FUNDED_MISSED_PAYMENTS', 'FUNDED_RENEWED', 'FUNDED_DEFAULTED'],
            'closed' => [
                'CLOSED_UNABLE_TO_SUBMIT',
                'CLOSED_MISSING_DOCUMENTS',
                'CLOSED_DECLINED',
                'CLOSED_OFFER_REJECTED',
                'CLOSED_KILLED_BY_FUNDER',
                'CLOSED_UNRESPONSIVE',
            ],
        ];

        $stages = [
            ['code' => 'LEAD', 'name' => 'Lead', 'kanban_group' => 'lead', 'probability' => 100],
            ['code' => 'NEW_APPLICATION', 'name' => 'New application', 'kanban_group' => 'new', 'probability' => 100],
            ['code' => 'MISSING_DOCUMENTS', 'name' => 'Missing documents', 'kanban_group' => 'new', 'probability' => 100],
            ['code' => 'READY_TO_SUBMIT', 'name' => 'Ready to submit', 'kanban_group' => 'new', 'probability' => 100],
            ['code' => 'CLOSED_UNABLE_TO_SUBMIT', 'name' => 'Closed unable to submit', 'kanban_group' => 'closed', 'probability' => 0],
            ['code' => 'CLOSED_MISSING_DOCUMENTS', 'name' => 'Closed missing documents', 'kanban_group' => 'closed', 'probability' => 0],
            ['code' => 'SUBMITTED', 'name' => 'Submitted', 'kanban_group' => 'submitted', 'probability' => 100],
            ['code' => 'RECEIVED_DLVC', 'name' => 'Received DL/VC', 'kanban_group' => 'submitted', 'probability' => 100],
            ['code' => 'OFFER_SELECTED', 'name' => 'Offer selected', 'kanban_group' => 'offers', 'probability' => 100],
            ['code' => 'OFFER_PITCHED', 'name' => 'Offer pitched', 'kanban_group' => 'offers', 'probability' => 100],
            ['code' => 'OFFER_ACCEPTED', 'name' => 'Offer accepted', 'kanban_group' => 'offers', 'probability' => 100],
            ['code' => 'APPROVED', 'name' => 'Approved', 'kanban_group' => 'offers', 'probability' => 100],
            ['code' => 'CONTRACTS_REQUESTED', 'name' => 'Contracts requested', 'kanban_group' => 'contracts', 'probability' => 100],
            ['code' => 'CLOSED_DECLINED', 'name' => 'Closed declined', 'kanban_group' => 'closed', 'probability' => 0],
            ['code' => 'CLOSED_OFFER_REJECTED', 'name' => 'Closed offer rejected', 'kanban_group' => 'closed', 'probability' => 0],
            ['code' => 'REPRICING', 'name' => 'Repricing', 'kanban_group' => 'offers', 'probability' => 100],
            ['code' => 'CONTRACTS_SENT', 'name' => 'Contracts sent', 'kanban_group' => 'contracts', 'probability' => 100],
            ['code' => 'FUNDED_DEFAULTED', 'name' => 'Funded defaulted', 'kanban_group' => 'funded', 'probability' => 100],
            ['code' => 'FINAL_REVIEW', 'name' => 'Final review', 'kanban_group' => 'offers', 'probability' => 100],
            ['code' => 'CLOSED_KILLED_BY_FUNDER', 'name' => 'Closed killed by funder', 'kanban_group' => 'closed', 'probability' => 0],
            ['code' => 'CONTRACTS_SIGNED', 'name' => 'Contracts signed', 'kanban_group' => 'contracts', 'probability' => 100],
            ['code' => 'FUNDED', 'name' => 'Funded', 'kanban_group' => 'funded', 'probability' => 100],
            ['code' => 'FUNDED_UP_FOR_RENEWAL', 'name' => 'Funded up for renewal', 'kanban_group' => 'funded', 'probability' => 100],
            ['code' => 'FUNDED_MISSED_PAYMENTS', 'name' => 'Funded missed payments', 'kanban_group' => 'funded', 'probability' => 100],
            ['code' => 'FUNDED_RENEWED', 'name' => 'Funded renewed', 'kanban_group' => 'funded', 'probability' => 100],
            ['code' => 'RESUBMITTING', 'name' => 'Resubmitting', 'kanban_group' => 'submitted', 'probability' => 100],
            ['code' => 'CLOSED_UNRESPONSIVE', 'name' => 'Closed unresponsive', 'kanban_group' => 'closed', 'probability' => 0],
        ];

        foreach ($stages as $index => $stage) {
            $group = $stage['kanban_group'];

            $stages[$index]['sort_order'] = $index + 1;
            $stages[$index]['kanban_group_label'] = $groupLabels[$group];
            $stages[$index]['kanban_group_sort'] = array_search($stage['code'], $groupStageOrder[$group], true);
        }

        return $stages;
    }

    /**
     * Documented pipeline stage codes, in enum order.
     */
    public static function pipelineStageCodes(): array
    {
        return array_column(static::pipelineStages(), 'code');
    }

    /**
     * Kanban groups with the stage codes they display.
     */
    public static function kanbanGroups(): array
    {
        $groups = [];

        foreach (static::pipelineStages() as $stage) {
            $key = $stage['kanban_group'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'code' => $key,
                    'label' => $stage['kanban_group_label'],
                    'stages' => [],
                ];
            }

            $groups[$key]['stages'][] = $stage['code'];
        }

        return array_values($groups);
    }

    /**
     * Attachment bucket codes and human labels.
     */
    public static function attachmentBuckets(): array
    {
        return [
            'bank_statements' => 'Bank statements',
            'application' => 'Application',
            'more_stips' => 'More stips',
            'dl_vc' => 'DL/VC',
            'closing' => 'Closing',
            'other' => 'Other',
        ];
    }

    /**
     * Map a legacy pipeline stage code onto the documented enum.
     */
    public static function mapLegacyStageCode(?string $code): string
    {
        if (in_array($code, static::pipelineStageCodes(), true)) {
            return $code;
        }

        return match ($code) {
            'new' => 'NEW_APPLICATION',
            'follow-up', 'prospect', 'negotiation' => 'LEAD',
            'won' => 'FUNDED',
            'lost' => 'CLOSED_DECLINED',
            default => static::DEFAULT_STAGE_CODE,
        };
    }

    /**
     * Whether the code is a funded (won-like) stage, including the legacy "won" code.
     */
    public static function isFundedStageCode(?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        return $code === 'won' || str_starts_with($code, 'FUNDED');
    }

    /**
     * Whether the code is a closed (lost-like) stage, including the legacy "lost" code.
     */
    public static function isClosedStageCode(?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        return $code === 'lost' || str_starts_with($code, 'CLOSED_');
    }

    /**
     * Whether the code is a terminal (funded or closed) stage.
     */
    public static function isTerminalStageCode(?string $code): bool
    {
        return static::isFundedStageCode($code) || static::isClosedStageCode($code);
    }

    /**
     * Funded stage codes from the documented enum, plus the legacy "won" code.
     */
    public static function fundedStageCodes(): array
    {
        return array_values(array_filter(
            array_merge(static::pipelineStageCodes(), ['won']),
            fn (string $code) => static::isFundedStageCode($code)
        ));
    }

    /**
     * Closed stage codes from the documented enum, plus the legacy "lost" code.
     */
    public static function closedStageCodes(): array
    {
        return array_values(array_filter(
            array_merge(static::pipelineStageCodes(), ['lost']),
            fn (string $code) => static::isClosedStageCode($code)
        ));
    }

    /**
     * Default stage for a newly created lead on the given pipeline.
     */
    public static function defaultStageForPipeline($pipeline)
    {
        if (! $pipeline) {
            return null;
        }

        return $pipeline->stages->firstWhere('code', static::DEFAULT_STAGE_CODE)
            ?? $pipeline->stages->first();
    }

    /**
     * Returns the rotten days
     */
    public function getRottenDaysAttribute()
    {
        if (! $this->stage) {
            return 0;
        }

        if (static::isTerminalStageCode($this->stage->code)) {
            return 0;
        }

        if (! $this->created_at) {
            return 0;
        }

        $rottenDate = $this->created_at->addDays($this->pipeline->rotten_days);

        return $rottenDate->diffInDays(Carbon::now(), false);
    }
}
