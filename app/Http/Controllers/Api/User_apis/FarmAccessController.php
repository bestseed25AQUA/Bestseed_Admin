<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmAccessGrant;
use App\Models\Manager;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * QR + PIN based farm access sharing.
 *
 * A farmer issues a grant for one of their farms (role, permissions, duration).
 * The QR carries only an opaque token; permissions, expiry and PIN are resolved
 * server-side so a code can be revoked or expired after it has been shared.
 *
 * Redeeming is two steps by design: scanning identifies the grant, then the PIN
 * confirms the holder is the intended person.
 */
class FarmAccessController extends Controller
{
    /** Wrong-PIN attempts allowed before a grant is locked. */
    private const MAX_PIN_ATTEMPTS = 5;

    /**
     * Resolve a farm the caller actually owns, or abort.
     *
     * Access codes are the farm's keys: only the owner may mint, list or revoke
     * them. A manager holding edit rights must not be able to hand out further
     * access, so this deliberately checks ownership rather than a permission
     * flag.
     */
    private function ownedFarm(Request $request, $farmId): Farm
    {
        $farm = Farm::find($farmId);

        if (!$farm) {
            throw new HttpResponseException(response()->json([
                'status'  => false,
                'message' => 'Farm not found',
            ], 404));
        }

        if ((int) $farm->farmer_id !== (int) $request->user()->id) {
            throw new HttpResponseException(response()->json([
                'status'  => false,
                'message' => 'Only the farm owner can manage access codes.',
            ], 403));
        }

        return $farm;
    }

    /**
     * POST /api/farmer/farm/{farm}/access/generate
     *
     * Creates a grant and returns the QR token plus the PIN. This is the only
     * response that includes the PIN alongside a freshly generated token.
     */
    public function generate(Request $request, $farmId)
    {
        $farm = $this->ownedFarm($request, $farmId);

        $validator = validator($request->all(), [
            'role'          => ['required', Rule::in(['manager', 'partner'])],
            'duration_days' => 'required|integer|min:1|max:365',
            'pin'           => 'required|digits:4',
            'view_access'   => 'nullable|boolean',
            'edit_access'   => 'nullable|boolean',
            'create_access' => 'nullable|boolean',
            'delete_access' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $durationDays = (int) $request->input('duration_days');

        $grant = FarmAccessGrant::create([
            'farm_id'       => $farm->id,
            'issued_by'     => $farm->farmer_id,
            'token'         => (string) Str::uuid(),
            'pin_secret'    => Crypt::encryptString($request->input('pin')),
            'role'          => $request->input('role'),
            'view_access'   => (int) $request->boolean('view_access'),
            'edit_access'   => (int) $request->boolean('edit_access'),
            'create_access' => (int) $request->boolean('create_access'),
            'delete_access' => (int) $request->boolean('delete_access'),
            'duration_days' => $durationDays,
            'expires_at'    => now()->addDays($durationDays),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Access QR generated successfully',
            'data'    => $this->presentGrant($grant, includePin: true),
        ], 201);
    }

    /**
     * GET /api/farmer/farm/{farm}/access?role=partner
     *
     * Backs the "QR CODE" screen — every code the farmer issued, newest first,
     * with its PIN so it can be re-shared.
     */
    public function index(Request $request, $farmId)
    {
        $this->ownedFarm($request, $farmId);

        $query = FarmAccessGrant::where('farm_id', $farmId)->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $grants = $query->get()->map(
            fn (FarmAccessGrant $grant) => $this->presentGrant($grant, includePin: true)
        );

        return response()->json([
            'status'  => true,
            'message' => 'Access codes fetched successfully',
            'data'    => $grants,
        ]);
    }

    /**
     * POST /api/farmer/access/redeem  { token }
     *
     * Step 1 of scanning: resolves the QR without granting anything yet, so the
     * scanner screen can show which farm is being joined before asking for the
     * PIN. No permissions are returned here.
     */
    public function redeem(Request $request)
    {
        $validator = validator($request->all(), ['token' => 'required|string']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Invalid QR code'], 422);
        }

        $grant = FarmAccessGrant::with('farm')
            ->where('token', $request->input('token'))
            ->first();

        if (!$grant) {
            return response()->json(['status' => false, 'message' => 'This QR code is not valid'], 404);
        }

        if ($error = $this->rejectionReason($grant)) {
            return response()->json(['status' => false, 'message' => $error], 409);
        }

        return response()->json([
            'status'  => true,
            'message' => 'QR verified. Enter the PIN to continue.',
            'data'    => [
                'token'     => $grant->token,
                'farm_id'   => $grant->farm_id,
                'farm_name' => $grant->farm->farm_name ?? null,
                'role'      => $grant->role,
                'expires_at'=> optional($grant->expires_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/farmer/access/verify-pin  { token, pin, name?, phone? }
     *
     * Step 2 of scanning: confirms the PIN, then creates (or updates) the
     * manager/partner row that actually carries the permissions.
     */
    public function verifyPin(Request $request)
    {
        $validator = validator($request->all(), [
            'token' => 'required|string',
            'pin'   => 'required|digits:4',
            'name'  => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $grant = FarmAccessGrant::with('farm')
            ->where('token', $request->input('token'))
            ->first();

        if (!$grant) {
            return response()->json(['status' => false, 'message' => 'This QR code is not valid'], 404);
        }

        if ($error = $this->rejectionReason($grant)) {
            return response()->json(['status' => false, 'message' => $error], 409);
        }

        if ($grant->pin_attempts >= self::MAX_PIN_ATTEMPTS) {
            return response()->json([
                'status'  => false,
                'message' => 'Too many incorrect attempts. Ask the farmer to share a new QR.',
            ], 429);
        }

        if (!hash_equals((string) $grant->pin(), (string) $request->input('pin'))) {
            $grant->increment('pin_attempts');

            $left = max(0, self::MAX_PIN_ATTEMPTS - $grant->pin_attempts);

            return response()->json([
                'status'            => false,
                'message'           => 'Incorrect PIN. Please try again.',
                'attempts_remaining'=> $left,
            ], 401);
        }

        // PIN correct — attach the person to the farm inside a transaction so a
        // half-written manager row can never outlive a failed grant update.
        $manager = DB::transaction(function () use ($grant, $request) {
            $manager = Manager::updateOrCreate(
                [
                    'farm_id' => $grant->farm_id,
                    'phone'   => $request->input('phone') ?: 'grant-' . $grant->id,
                ],
                [
                    'name'          => $request->input('name') ?: ucfirst($grant->role),
                    'is_partner'    => $grant->role === 'partner' ? 1 : 0,
                    'view_access'   => $grant->view_access,
                    'edit_access'   => $grant->edit_access,
                    'create_access' => $grant->create_access,
                    'delete_access' => $grant->delete_access,
                ]
            );

            $grant->update([
                'manager_id'   => $manager->id,
                'redeemed_at'  => now(),
                'redeemed_by'  => $request->user()->id, // the farmer who scanned, not the owner
                'pin_attempts' => 0,
            ]);

            return $manager;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Access granted successfully.',
            'data'    => [
                'farm_id'     => $grant->farm_id,
                'farm_name'   => $grant->farm->farm_name ?? null,
                'manager_id'  => $manager->id,
                'role'        => $grant->role,
                'expires_at'  => optional($grant->expires_at)->toIso8601String(),
                'permissions' => [
                    'view'   => (bool) $grant->view_access,
                    'edit'   => (bool) $grant->edit_access,
                    'create' => (bool) $grant->create_access,
                    'delete' => (bool) $grant->delete_access,
                ],
            ],
        ]);
    }

    /**
     * GET /api/farmer/farm/{farm}/grantees?role=partner
     *
     * Backs "Scanned Details" — people who actually redeemed a code.
     */
    public function grantees(Request $request, $farmId)
    {
        $this->ownedFarm($request, $farmId);

        $query = FarmAccessGrant::with(['manager', 'farm'])
            ->where('farm_id', $farmId)
            ->whereNotNull('redeemed_at')
            ->whereNull('revoked_at')
            ->latest('redeemed_at');

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $data = $query->get()->map(function (FarmAccessGrant $grant) {
            return [
                'grant_id'       => $grant->id,
                'manager_id'     => $grant->manager_id,
                'name'           => $grant->manager->name ?? null,
                'phone'          => $grant->manager->phone ?? null,
                'farm_name'      => $grant->farm->farm_name ?? null,
                'role'           => $grant->role,
                'redeemed_at'    => optional($grant->redeemed_at)->toIso8601String(),
                'expires_at'     => optional($grant->expires_at)->toIso8601String(),
                'days_remaining' => $grant->daysRemaining(),
                'status'         => $grant->statusLabel(),
                'permissions'    => [
                    'view'   => (bool) $grant->view_access,
                    'edit'   => (bool) $grant->edit_access,
                    'create' => (bool) $grant->create_access,
                    'delete' => (bool) $grant->delete_access,
                ],
            ];
        });

        return response()->json([
            'status'  => true,
            'message' => 'Scanned details fetched successfully',
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/farmer/access/{grant}/revoke
     *
     * Backs the "Remove" button. Revoking also strips the manager row's
     * permissions so an already-redeemed grant stops working immediately.
     */
    public function revoke(Request $request, $grantId)
    {
        $grant = FarmAccessGrant::find($grantId);

        if (!$grant) {
            return response()->json(['status' => false, 'message' => 'Access record not found'], 404);
        }

        $this->ownedFarm($request, $grant->farm_id);

        DB::transaction(function () use ($grant) {
            $grant->update(['revoked_at' => now()]);

            if ($grant->manager_id) {
                Manager::where('id', $grant->manager_id)->update([
                    'view_access'   => 0,
                    'edit_access'   => 0,
                    'create_access' => 0,
                    'delete_access' => 0,
                ]);
            }
        });

        return response()->json([
            'status'  => true,
            'message' => 'Access removed successfully',
        ]);
    }

    /** Shared shape for a grant row. */
    private function presentGrant(FarmAccessGrant $grant, bool $includePin = false): array
    {
        $payload = [
            'id'             => $grant->id,
            'farm_id'        => $grant->farm_id,
            'token'          => $grant->token,
            'role'           => $grant->role,
            'duration_days'  => $grant->duration_days,
            'expires_at'     => optional($grant->expires_at)->toIso8601String(),
            'days_remaining' => $grant->daysRemaining(),
            'status'         => $grant->statusLabel(),
            'created_at'     => optional($grant->created_at)->toIso8601String(),
            'permissions'    => [
                'view'   => (bool) $grant->view_access,
                'edit'   => (bool) $grant->edit_access,
                'create' => (bool) $grant->create_access,
                'delete' => (bool) $grant->delete_access,
            ],
        ];

        if ($includePin) {
            $payload['pin'] = $grant->pin();
        }

        return $payload;
    }

    /** Human-readable reason a grant cannot be used, or null when it is fine. */
    private function rejectionReason(FarmAccessGrant $grant): ?string
    {
        if ($grant->revoked_at !== null) {
            return 'This access has been removed by the farmer.';
        }

        if ($grant->expires_at !== null && $grant->expires_at->isPast()) {
            return 'This QR code has expired.';
        }

        return null;
    }
}
