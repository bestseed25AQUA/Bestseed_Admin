<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * GET /api/farmer/contacts
     * Returns all active contacts
     */
    public function index()
    {
        try {
            $contacts = Contact::where('status', 1)
                ->orderBy('id', 'asc')
                ->get();

            if ($contacts->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active contacts found',
                    'contacts' => []
                ], 200);
            }

            $contactList = $contacts->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'phone' => $contact->phone,
                    'whatsapp' => $contact->whatsapp,
                    'label' => $contact->label,
                ];
            });

            return response()->json([
                'status' => true,
                'contacts' => $contactList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
