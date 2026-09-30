<?php

namespace App\Http\Middleware;

use App\Models\Farm;
use App\Models\Manager;
use App\Models\Tank;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse writes to a farm the owner is no longer paying for.
 *
 * Most farm routes get this from [EnsureFarmAccess], which downgrades the
 * permission itself. This exists for the handful that were written before
 * access control and carry no ability: the manager and partner endpoints.
 * They keep their own ownership checks; this only adds the subscription rule.
 *
 * `farm.unlocked` finds the farm the usual way — route parameter, `farm_id`,
 * or `tank_id`. `farm.unlocked:member` finds it through the manager or partner
 * named in the body, which is all those routes are given.
 */
class EnsureFarmNotLocked
{
    private const MEMBER_KEYS = ['manager_id', 'partner_id', 'member_id', 'id'];

    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function handle(Request $request, Closure $next, string $source = 'farm'): Response
    {
        $farmId = $source === 'member'
            ? $this->farmIdFromMember($request)
            : $this->farmIdFromRequest($request);

        // Nothing to judge. The route's own guards still run; this middleware
        // only ever adds a refusal, never an approval.
        if ($farmId === null) {
            return $next($request);
        }

        $farm = Farm::find($farmId);

        if ($farm && $this->subscriptions->isFarmLocked($farm)) {
            return response()->json([
                'status'  => false,
                'locked'  => true,
                'message' => 'This farm is read-only until you renew your subscription. '
                    . 'You can still view it, harvest tanks and download reports.',
            ], 403);
        }

        return $next($request);
    }

    private function farmIdFromRequest(Request $request): ?int
    {
        foreach (['farm', 'farm_id', 'id'] as $key) {
            $value = $request->route($key) ?? $request->input($key);

            if ($this->isPositiveInt($value)) {
                return (int) $value;
            }
        }

        $tankId = $request->input('tank_id');

        return $this->isPositiveInt($tankId)
            ? Tank::where('id', (int) $tankId)->value('farm_id')
            : null;
    }

    private function farmIdFromMember(Request $request): ?int
    {
        foreach (self::MEMBER_KEYS as $key) {
            $value = $request->input($key);

            if ($this->isPositiveInt($value)) {
                return Manager::withTrashed()->where('id', (int) $value)->value('farm_id');
            }
        }

        return null;
    }

    private function isPositiveInt($value): bool
    {
        return $value !== null
            && $value !== ''
            && ctype_digit((string) $value)
            && (int) $value > 0;
    }
}
