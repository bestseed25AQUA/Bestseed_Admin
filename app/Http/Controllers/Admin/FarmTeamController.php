<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Managers and partners — the people attached to a farm.
 *
 * Both live in the `managers` table and are told apart by `is_partner`, so one
 * controller covers both and the role is just a filter.
 */
class FarmTeamController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:farm-management.view')->only(['index']);
        $this->middleware('permission:farm-management.create')->only(['create', 'store']);
        $this->middleware('permission:farm-management.update')->only(['edit', 'update']);
        $this->middleware('permission:farm-management.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $team = Manager::with('farm.farmer')
            ->when($request->filled('farm_id'), fn ($q) => $q->where('farm_id', $request->input('farm_id')))
            ->when($request->input('role') === 'manager', fn ($q) => $q->managers())
            ->when($request->input('role') === 'partner', fn ($q) => $q->partners())
            ->orderByDesc('id')
            ->get();

        $farms = Farm::with('farmer')->orderBy('farm_name')->get();

        return view('admin.farm-management.team.index', compact('team', 'farms'));
    }

    public function create(Request $request)
    {
        return view('admin.farm-management.team.create', [
            'farms'         => Farm::with('farmer')->orderBy('farm_name')->get(),
            'selectedFarm'  => $request->input('farm_id'),
            'selectedRole'  => $request->input('role', 'manager'),
        ]);
    }

    public function store(Request $request)
    {
        // Several people at once, like the app's Setup Access screen: the form
        // collects a list of mobile numbers and posts them as `people`.
        // `phone` is still accepted on its own so an older bookmark or a
        // direct POST keeps working.
        $people = $this->peopleFrom($request);

        if (empty($people)) {
            return redirect()->back()->withInput()
                ->with('error', 'Add at least one person by their mobile number.');
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $farmId = $request->input('farm_id');

        $added   = [];
        $skipped = [];

        try {
            foreach ($people as $person) {
                $phone = $person['phone'];

                // (farm_id, phone) is unique — the same person can be on two
                // farms, but not twice on one. Skipped rather than refused, so
                // adding four people does not fail because one was already on.
                if (Manager::where('farm_id', $farmId)->where('phone', $phone)->exists()) {
                    $skipped[] = $phone;
                    continue;
                }

                // Make sure the person exists as a farmer, the way the app
                // does. A number nobody has registered becomes a farmer here
                // and now, with the same shape the login flow creates. When
                // they later ask for an OTP, firstOrCreate finds THIS row
                // rather than making a second one, so the farm is already
                // waiting for them the first time they sign in.
                $farmer = Farmer::firstOrCreate(
                    ['mobile' => $phone],
                    ['role' => 'farmer']
                );

                // Only a REAL name, and only when they have none: the form
                // sends the number itself for someone being created, and
                // writing that into first_name would show "9912821122" as
                // their name in the app forever.
                if (blank($farmer->first_name)
                    && $person['name'] !== ''
                    && $person['name'] !== $phone) {
                    $farmer->forceFill(['first_name' => $person['name']])->save();
                }

                Manager::create($this->payloadFor($request, $person));
                $added[] = $phone;
            }
        } catch (\Exception $e) {
            Log::error('Admin team create failed', ['error' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'Could not add this person: ' . $e->getMessage());
        }

        if (empty($added)) {
            return redirect()->back()->withInput()
                ->with('error', 'Everyone chosen is already on this farm.');
        }

        $msg = count($added) . ' ' . (count($added) === 1 ? 'person' : 'people') . ' added.';

        if ($skipped) {
            $msg .= ' ' . count($skipped) . ' already on this farm: ' . implode(', ', $skipped) . '.';
        }

        return redirect()->route('farm-management.team.index')->with('success', $msg);
    }

    /**
     * The people this submit is adding.
     *
     * `people` is a JSON array of `{phone, name}` from the form's chosen list.
     * A lone `phone` field is still honoured so a direct POST keeps working.
     *
     * @return array<int, array{phone: string, name: string}>
     */
    private function peopleFrom(Request $request): array
    {
        $rows = json_decode((string) $request->input('people', ''), true);

        if (!is_array($rows)) {
            $rows = [];
        }

        $out  = [];
        $seen = [];

        foreach ($rows as $row) {
            $phone = preg_replace('/\D/', '', (string) ($row['phone'] ?? ''));

            // Ten digits or it is not a number we can create a login for.
            // Duplicates within one submit collapse to a single entry.
            if (strlen($phone) !== 10 || isset($seen[$phone])) {
                continue;
            }

            $seen[$phone] = true;
            $out[] = [
                'phone' => $phone,
                'name'  => trim((string) ($row['name'] ?? '')),
            ];
        }

        if ($out === []) {
            $single = preg_replace('/\D/', '', (string) $request->input('phone', ''));

            if (strlen($single) === 10) {
                $out[] = [
                    'phone' => $single,
                    'name'  => trim((string) $request->input('name', '')),
                ];
            }
        }

        return $out;
    }

    public function edit($id)
    {
        return view('admin.farm-management.team.edit', [
            'member' => Manager::with('farm')->findOrFail($id),
            'farms'  => Farm::with('farmer')->orderBy('farm_name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $member = Manager::findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules($id), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $clash = Manager::where('farm_id', $request->input('farm_id'))
            ->where('phone', $request->input('phone'))
            ->where('id', '!=', $member->id)
            ->exists();

        if ($clash) {
            return redirect()->back()->withInput()
                ->with('error', 'That phone number is already on this farm.');
        }

        try {
            $member->update($this->payload($request));

            return redirect()->route('farm-management.team.index')
                ->with('success', 'Access updated successfully.');
        } catch (\Exception $e) {
            Log::error('Admin team update failed', ['manager_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'Could not update this person: ' . $e->getMessage());
        }
    }

    /**
     * Remove a person from the team.
     *
     * This also used to revoke the QR grant that produced them, so the code
     * could not be redeemed again and silently recreate the row. There are no
     * codes any more, so deleting the row is the whole job.
     */
    public function destroy($id)
    {
        $member = Manager::findOrFail($id);

        try {
            $member->delete();

            return redirect()->route('farm-management.team.index')
                ->with('success', 'Removed.');
        } catch (\Exception $e) {
            Log::error('Admin team delete failed', ['manager_id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not remove this person: ' . $e->getMessage());
        }
    }

    /**
     * GET /admin/farm-management/team/lookup?mobile=##########
     *
     * Find the registered farmer behind a mobile number, mirroring the app's
     * /farmer/farmers/search: ten digits, one exact answer, or none.
     *
     * Deliberately NOT a partial-name search. Picking the wrong "Ramesh" out
     * of a suggestion list hands a stranger the farm, so the number is the
     * only key.
     *
     * A miss is not an error — the caller is told plainly that nobody holds
     * this number, so the form can offer to add them by it.
     */
    public function lookup(Request $request)
    {
        $mobile = preg_replace('/\D/', '', (string) $request->query('mobile', ''));

        if (strlen($mobile) !== 10) {
            return response()->json([
                'ok'      => false,
                'message' => 'Enter the full 10-digit mobile number.',
            ], 422);
        }

        $farmer = Farmer::where('mobile', $mobile)
            ->first(['id', 'first_name', 'last_name', 'mobile']);

        if (!$farmer) {
            return response()->json([
                'ok'     => true,
                'found'  => false,
                'mobile' => $mobile,
                'message'=> 'No one is registered with this number yet. Adding will create them.',
            ]);
        }

        return response()->json([
            'ok'     => true,
            'found'  => true,
            'mobile' => $mobile,
            'id'     => $farmer->id,
            'name'   => trim($farmer->first_name . ' ' . $farmer->last_name),
        ]);
    }

    private function rules($ignoreId = null): array
    {
        return [
            'farm_id'       => 'required|integer|exists:farms,id',
            // Derived, not typed: the form finds the person by number and
            // fills this from their profile. Nullable so a save cannot fail
            // with "name is required" against a field nobody can see.
            'name'          => 'nullable|string|max:255',
            // Per person now — validated in peopleFrom(). Nullable so a
            // multi-person submit is not rejected for having no single phone.
            'phone'         => 'nullable|digits:10',
            'is_partner'    => ['required', Rule::in([0, 1, '0', '1'])],
            'view_access'        => 'nullable|boolean',
            'edit_access'        => 'nullable|boolean',
            'tank_status_access' => 'nullable|boolean',
            'total_feed_access'  => 'nullable|boolean',
            'create_access'      => 'nullable|boolean',
            'delete_access'      => 'nullable|boolean',
        ];
    }

    private function messages(): array
    {
        return [
            'farm_id.required' => 'Please choose the farm this person belongs to.',
            'phone.digits'     => 'Phone number must be exactly 10 digits.',
        ];
    }

    /**
     * The name to store against this team member.
     *
     * Whatever the form derived, else the registered farmer's own name, else
     * the number — so the row is never nameless even if the browser lookup did
     * not run.
     */
    private function memberNameFor(Request $request): string
    {
        $typed = trim((string) $request->input('name'));

        if ($typed !== '' && $typed !== $request->input('phone')) {
            return $typed;
        }

        $farmer = Farmer::where('mobile', $request->input('phone'))->first();
        $known  = $farmer ? trim($farmer->first_name . ' ' . $farmer->last_name) : '';

        return $known !== '' ? $known : (string) $request->input('phone');
    }

    /**
     * One team-member row, for a person out of the chosen list.
     *
     * @param  array{phone: string, name: string}  $person
     */
    private function payloadFor(Request $request, array $person): array
    {
        $name = $person['name'];

        if ($name === '' || $name === $person['phone']) {
            // Fall back to the registered farmer's own name, so the row is not
            // left showing a bare number when we know who they are.
            $farmer = Farmer::where('mobile', $person['phone'])->first();
            $known  = $farmer ? trim($farmer->first_name . ' ' . $farmer->last_name) : '';
            $name   = $known !== '' ? $known : $person['phone'];
        }

        return [
            'farm_id'       => $request->input('farm_id'),
            'name'          => $name,
            'phone'         => $person['phone'],
            'is_partner'    => (int) $request->input('is_partner'),
            'view_access'        => (int) $request->boolean('view_access'),
            'edit_access'        => (int) $request->boolean('edit_access'),
            'tank_status_access' => (int) $request->boolean('tank_status_access'),
            'total_feed_access'  => (int) $request->boolean('total_feed_access'),
            'create_access'      => (int) $request->boolean('create_access'),
            'delete_access'      => (int) $request->boolean('delete_access'),
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'farm_id'       => $request->input('farm_id'),
            'name'          => $this->memberNameFor($request),
            'phone'         => $request->input('phone'),
            'is_partner'    => (int) $request->input('is_partner'),
            'view_access'        => (int) $request->boolean('view_access'),
            'edit_access'        => (int) $request->boolean('edit_access'),
            'tank_status_access' => (int) $request->boolean('tank_status_access'),
            'total_feed_access'  => (int) $request->boolean('total_feed_access'),
            'create_access'      => (int) $request->boolean('create_access'),
            'delete_access'      => (int) $request->boolean('delete_access'),
        ];
    }
}
