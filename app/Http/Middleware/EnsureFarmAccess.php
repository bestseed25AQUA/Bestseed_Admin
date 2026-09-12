<?php

namespace App\Http\Middleware;

use App\Models\Farm;
use App\Models\Tank;
use App\Services\FarmAccessService;
use App\Support\FarmPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a farm route behind an ability: farm.access:view, farm.access:edit, and
 * so on. Several may be given, separated by `|` — `farm.access:create|edit`
 * passes when the caller holds EITHER.
 *
 * The controllers below this middleware were written before access control
 * existed and each locates its farm differently — a route parameter, a body
 * field, or (in one case) a request header. Rather than rewrite all of them,
 * the middleware resolves the farm the same way they do and refuses the
 * request before the controller runs.
 *
 * The resolved farm and permission are attached to the request so a controller
 * can read them instead of querying again:
 *
 *     $farm = $request->attributes->get('farm');
 *     $permission = $request->attributes->get('farm_permission');
 */
class EnsureFarmAccess
{
    /** Route parameters that carry a farm id, in the order we trust them. */
    private const FARM_ROUTE_KEYS = ['farm', 'farm_id', 'id'];

    /** Request fields that carry a farm id. */
    private const FARM_INPUT_KEYS = ['farm_id'];

    /** Request fields (body or header) that carry a tank id we can map to a farm. */
    private const TANK_KEYS = ['tank_id'];

    public function __construct(private readonly FarmAccessService $access)
    {
    }

    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $farmer = $request->user();

        if (!$farmer) {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $farmId = $this->resolveFarmId($request);

        if ($farmId === null) {
            return response()->json([
                'status'  => false,
                'message' => 'Farm could not be identified for this request.',
            ], 422);
        }

        $farm = Farm::find($farmId);

        if (!$farm) {
            return response()->json([
                'status'  => false,
                'message' => 'Farm not found',
            ], 404);
        }

        $permission = $this->access->permissionFor($farmer->id, $farm);

        // `create|edit` passes when the caller holds EITHER.
        //
        // Some actions are reachable through more than one ability: recording a
        // meal is a create, but correcting one is an edit, and the same screen
        // does both — so a manager given edit could open the boxes, type, and
        // be refused on save for a distinction they never saw. Where the split
        // is real (delete, tank_status) the routes still name one ability.
        $allowed = array_filter(explode('|', $ability));

        $granted = false;
        foreach ($allowed as $one) {
            if ($permission->allows($one)) {
                $granted = true;
                break;
            }
        }

        if (!$granted) {
            // Read as a list, so a refusal names every ability that would have
            // worked rather than only the first.
            $needed = implode(' or ', $allowed);

            return response()->json([
                'status'  => false,
                'message' => $permission->isDenied()
                    ? 'You do not have access to this farm.'
                    : "Your access to this farm does not allow you to {$needed}.",
            ], 403);
        }

        $request->attributes->set('farm', $farm);
        $request->attributes->set('farm_permission', $permission);

        return $next($request);
    }

    /**
     * Find the farm this request is about, trying the same places the
     * controllers do: route parameter, then body field, then tank id
     * (body or header), which we resolve to its farm.
     */
    private function resolveFarmId(Request $request): ?int
    {
        foreach (self::FARM_ROUTE_KEYS as $key) {
            $value = $request->route($key);

            if ($this->isPositiveInt($value)) {
                return (int) $value;
            }
        }

        foreach (self::FARM_INPUT_KEYS as $key) {
            $value = $request->input($key);

            if ($this->isPositiveInt($value)) {
                return (int) $value;
            }
        }

        foreach (self::TANK_KEYS as $key) {
            $value = $request->input($key) ?? $request->header($key);

            if ($this->isPositiveInt($value)) {
                return Tank::where('id', (int) $value)->value('farm_id');
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
