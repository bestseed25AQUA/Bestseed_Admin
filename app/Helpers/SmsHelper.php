<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class SmsHelper
{
    // $messageId is the DLT-approved template id. Both templates now use
    // the SMS Retriever hash derived from the Play App Signing certificate
    // (Google's re-signing key), so auto-fill works on Play-Store-installed
    // builds. Sideloaded release APKs (signed only with upload-keystore.jks)
    // no longer auto-fill — acceptable trade-off since real users install
    // from Play Store.
    //   - Default (USER login): 221702, hash ojNjksd+KK0
    //   - Driver login (passed explicitly in DriverAuthController): 221703,
    //     hash Cpa0qamvt0R
    public static function sendSms($mobile, $message, $messageId = 221702)
    {
        $apiKey = "Fag2ue8P5zwkiKdvJI1ZoxWH3sTNEUYmlMRhnA7jCS9B4GqrtVlFw9i30yNe6M4vhu27mKsgQxR1YOqP";

        $url = 'https://www.fast2sms.com/dev/bulkV2?' . http_build_query([
                'authorization' => $apiKey,  // MUST be in URL for GET
                'route' => 'dlt',
                'sender_id' => 'BSEEDA',
                'message' => $messageId,
                'variables_values' => $message,
                'numbers' => $mobile,
            ]);

            $response = Http::get($url);

        // Check response
        $data = $response->json();
        \Log::info('Fast2SMS GET Response:', $data);

        return $data;
    }
}
