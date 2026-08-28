<?php

namespace Webkul\Funder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Funder\Contracts\Submission as SubmissionContract;
use Webkul\Lead\Models\LeadProxy;

class Submission extends Model implements SubmissionContract
{
    public const STATUS_SENT = 'sent';

    public const STATUS_ERRORED = 'errored';

    protected $table = 'submissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'lead_id',
        'funder_id',
        'route',
        'status',
        'error_message',
    ];

    /**
     * Lead this submission belongs to.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Funder this submission was sent to.
     */
    public function funder(): BelongsTo
    {
        return $this->belongsTo(FunderProxy::modelClass());
    }
}
