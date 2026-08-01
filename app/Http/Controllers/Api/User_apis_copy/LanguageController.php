<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * GET /api/farmer/language
     * Returns the current language of authenticated farmer
     */
    public function getLanguage(Request $request)
    {
        try {
            $farmer = $request->user(); // Returns Farmer model instance
            return response()->json([
                'language' => $farmer->language ?? 'en' // default to English
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch language',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/farmer/language
     * Update the farmer's language preference
     */
    public function updateLanguage(Request $request)
    {
        try {
            // Allowed languages
            $allowedLanguages = [
                'en', // English
                'te', // Telugu
                'hi', // Hindi
                'ta', // Tamil
                'kn', // Kannada
                'ml', // Malayalam
                'mr', // Marathi
                'gu', // Gujarati
                'pa', // Punjabi
                'bn', // Bengali
                'or', // Odia
                'ur', // Urdu
            ];

            // Validate input
            $request->validate([
                'language' => 'required|string|in:' . implode(',', $allowedLanguages),
            ]);

            $farmer = $request->user(); // Farmer instance
            $farmer->language = $request->language;
            $farmer->save();

            return response()->json([
                'message' => 'Language updated successfully',
                'language' => $farmer->language
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Input validation errors
            return response()->json([
                'message' => 'Invalid input',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            // Any other error
            return response()->json([
                'message' => 'Failed to update language',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
