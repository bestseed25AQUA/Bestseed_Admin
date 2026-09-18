<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

/**
 * What the app needs to know about a farmer's farm allowance.
 *
 * There is no payment here. The farmer picks a plan, calls the Farm Management
 * helpline, and an admin records it. So this endpoint answers two questions
 * only: may I add another farm, and if not, what am I being offered and who do
 * I ring.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * GET /api/farmer/subscription/status
     *
     * Called before the add-farm button opens the form, and again when the
     * Farm Management screen loads so an expiry warning can be shown in place.
     */
    public function status(Request $request)
    {
        $status = $this->subscriptions->statusFor($request->user()->id);

        // The number to ring, resolved server-side so the app does not have to
        // make a second call and reimplement the label matching.
        $status['contact'] = $this->helplineContact();

        return response()->json([
            'status' => true,
            'data'   => $status,
        ]);
    }

    /**
     * The Farm Management helpline, falling back to any active contact.
     *
     * A farmer looking at a plan must always be given a number. Returning null
     * because one slot is unconfigured turns a sale into a dead end.
     */
    private function helplineContact(): ?array
    {
        $contacts = Contact::where('status', 1)->orderBy('id')->get();

        if ($contacts->isEmpty()) {
            return null;
        }

        $preferred = $contacts->first(function ($contact) {
            return $this->labelMatches($contact->label, 'Farm Management Help');
        }) ?: $contacts->first();

        return [
            'id'       => $preferred->id,
            'label'    => $preferred->label,
            'phone'    => $preferred->phone,
            'whatsapp' => $preferred->whatsapp,
        ];
    }

    /** Same tolerant comparison the app uses, so free-text labels still match. */
    private function labelMatches(?string $label, string $target): bool
    {
        $normalise = fn ($value) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));

        return $normalise($label) === $normalise($target);
    }
}
