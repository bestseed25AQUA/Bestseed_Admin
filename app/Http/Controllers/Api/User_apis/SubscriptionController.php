<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Farm;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRequest;
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
     * POST /api/farmer/subscription/request
     *
     * Leave a request instead of ringing.
     *
     * Call and WhatsApp both need somebody to pick up; this does not. The farm
     * and package travel with it, so admin is not left guessing which farm a
     * farmer meant — the common case is a locked farm they want back.
     *
     * Nothing is granted here. It is a message with context.
     */
    public function requestSubscription(Request $request)
    {
        $validated = $request->validate([
            // Both optional: a farmer may be asking about a specific locked
            // farm, or simply asking for more.
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
            'plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $farmerId = (int) $request->user()->id;

        // A farm they do not own is not theirs to ask about.
        if (!empty($validated['farm_id'])) {
            $owns = Farm::where('id', $validated['farm_id'])
                ->where('farmer_id', $farmerId)
                ->exists();

            if (!$owns) {
                return response()->json([
                    'status'  => false,
                    'message' => 'That farm is not yours.',
                ], 403);
            }
        }

        // One open request at a time per farm.
        //
        // A farmer who taps twice, or comes back the next day because nobody
        // rang, should not fill the admin list with the same ask — the second
        // tap updates the first instead, and the admin still sees one row.
        $existing = SubscriptionRequest::where('farmer_id', $farmerId)
            ->where('farm_id', $validated['farm_id'] ?? null)
            ->open()
            ->latest('id')
            ->first();

        $plan = !empty($validated['plan_id'])
            ? SubscriptionPlan::find($validated['plan_id'])
            : null;

        $payload = [
            'farmer_id'  => $farmerId,
            'farm_id'    => $validated['farm_id'] ?? null,
            'plan_id'    => $plan?->id,
            'plan_label' => $plan?->label,
            'message'    => $validated['message'] ?? null,
            'status'     => SubscriptionRequest::PENDING,
        ];

        if ($existing) {
            $existing->update($payload);
            $req = $existing;
        } else {
            $req = SubscriptionRequest::create($payload);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Request sent. Our team will contact you shortly.',
            'data'    => [
                'id'         => $req->id,
                'status'     => $req->status,
                'created_at' => $req->created_at?->toIso8601String(),
            ],
        ], 201);
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
