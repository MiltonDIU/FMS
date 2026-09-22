<?php

namespace App\Exceptions;

/**
 * The HR API answered, understood us, and said no.
 *
 * Separate from every other \RuntimeException the client throws because those
 * mean something is wrong — the API is unreachable, unconfigured, returning
 * something that is not JSON. A refusal means the other end is working
 * perfectly and the answer is that we may not have this record.
 *
 * It still extends \RuntimeException, so the screens that already catch that
 * around a profile fetch keep catching this.
 *
 * The distinction matters most for the one refusal that arrives in bulk. A
 * sync run over the whole faculty reaches every non-academic employee on file,
 * and the ERP turns each one away with "Only academic persons information is
 * accessible from this api". That is the endpoint's scope, not a fault: nobody
 * can fix it, the same people will be turned away on the next run, and counting
 * them as failures buries the one or two records that genuinely did break.
 */
class HrApiRefusal extends \RuntimeException
{
    /**
     * @param  string  $reason  The vendor's own wording, unwrapped.
     */
    public function __construct(protected string $reason)
    {
        parent::__construct('The HR API refused the request: ' . $reason . '.');
    }

    /**
     * The vendor's message on its own, without our sentence around it.
     */
    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * Whether this is the ERP saying the employee is outside what the profile
     * endpoint serves, rather than anything being wrong.
     *
     * Matched on the wording because the vendor sends no code with it — the
     * whole response is `success: false` and a sentence. Kept loose enough to
     * survive small edits to that sentence ("Only academic person's…", a
     * changed article, different capitalisation) and tight enough not to
     * swallow an unrelated refusal: both halves have to be present.
     */
    public function isOutOfScope(): bool
    {
        $reason = mb_strtolower($this->reason);

        return str_contains($reason, 'academic')
            && (str_contains($reason, 'accessible') || str_contains($reason, 'access'));
    }
}
