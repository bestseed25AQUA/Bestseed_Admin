<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Log;

class ContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.view')->only(['index', 'show']);
        $this->middleware('permission:settings.create')->only(['create', 'store']);
        $this->middleware('permission:settings.update')->only(['edit', 'update']);
        $this->middleware('permission:settings.delete')->only(['destroy']);
    }

    public function index()
    {
        $contacts = Contact::orderByDesc('id')->paginate(10);
        return view('admin.contacts.index', compact('contacts'));
    }

    public function create()
    {
        return view('admin.contacts.create');
    }

    public function store(Request $request)
    {
        Log::info('Storing new contact', ['request' => $request->all()]);
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'nullable|digits:10',
                'whatsapp' => 'nullable|digits:10',
                'label' => 'required|in:' . implode(',', Contact::LABELS),
            ], [
                'phone.digits' => 'Phone number must be exactly 10 digits.',
                'whatsapp.digits' => 'WhatsApp number must be exactly 10 digits.',
                'label.required' => 'Please choose where this contact appears.',
                'label.in' => 'Invalid label selected.',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', ['errors' => $validator->errors()->all()]);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = $validator->validated();
            $data['status'] = $request->has('status') ? 1 : 0;

            // Multiple contacts can be active at once (one per slot/label). Keep
            // a single active contact PER label so each screen resolves to one
            // number — activating this one deactivates any other active row with
            // the same label.
            if ($data['status'] == 1) {
                Contact::where('status', 1)
                    ->where('label', $data['label'])
                    ->update(['status' => 0]);
            }

            Contact::create($data);

            Log::info('Contact created successfully', ['contact' => $data]);

            return redirect()->route('contacts.index')->with('success', 'Contact created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating contact', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong. Please try again.')->withInput();
        }
    }

    public function edit(Contact $contact)
    {
        return view('admin.contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|digits:10',
            'whatsapp' => 'nullable|digits:10',
            'label' => 'required|in:' . implode(',', Contact::LABELS),
        ], [
            'phone.digits' => 'Phone number must be exactly 10 digits.',
            'whatsapp.digits' => 'WhatsApp number must be exactly 10 digits.',
            'label.required' => 'Please choose where this contact appears.',
            'label.in' => 'Invalid label selected.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['status'] = $request->has('status') ? 1 : 0;

        // Keep a single active contact per label/slot — activating this one
        // deactivates any other active row sharing the same label.
        if ($data['status'] == 1) {
            Contact::where('status', 1)
                ->where('label', $data['label'])
                ->where('id', '!=', $contact->id)
                ->update(['status' => 0]);
        }

        $contact->update($data);

        return redirect()->route('contacts.index')->with('success', 'Contact updated successfully.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();
        return redirect()->route('contacts.index')->with('success', 'Contact deleted successfully.');
    }
}
