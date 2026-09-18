<?php

/**
 * Farm Management subscriptions.
 *
 * One farmer gets a small number of farms for free. Beyond that they buy time,
 * not farms: while a subscription is live they may create as many as they like,
 * and when it lapses they keep everything they made but cannot add more.
 *
 * The catalogue lives here rather than in the database because the prices are
 * fixed commercial terms, not per-deployment settings, and BOTH the admin
 * panel's dropdown and the app's bottom sheet read it. The app is served these
 * values over the API instead of hardcoding them, so a price change is a deploy
 * of this file alone and old installs pick it up without a store release.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Free allowance
    |--------------------------------------------------------------------------
    |
    | How many farms a farmer may own before a subscription is required. Only
    | farms they OWN count. Farms shared with them as a manager or partner are
    | somebody else's allowance and must never consume theirs.
    |
    */
    'free_farm_limit' => 2,

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | `months`    drives the expiry date, so the plan key is the only thing an
    |             admin picks and the dates cannot be typed wrong.
    | `reminders` is how many days before expiry to warn, largest first. A long
    |             plan warns earlier and more often because there is more to
    |             lose by letting it lapse unnoticed; a one-month plan warning
    |             30 days out would fire almost as soon as it was bought.
    |
    | Keys are stored in `farm_subscriptions.plan_key`. Never rename one without
    | a migration — existing rows point at it.
    |
    */
    'plans' => [

        'month_1' => [
            'label'     => '1 Month',
            'months'    => 1,
            'amount'    => 199,
            'reminders' => [7, 3, 2, 1],
        ],

        'month_3' => [
            'label'     => '3 Months',
            'months'    => 3,
            'amount'    => 549,
            'reminders' => [15, 10, 7, 3, 2, 1],
        ],

        'month_6' => [
            'label'     => '6 Months',
            'months'    => 6,
            'amount'    => 999,
            'reminders' => [30, 15, 10, 7, 3, 2, 1],
        ],

        'year_1' => [
            'label'     => '1 Year',
            'months'    => 12,
            'amount'    => 1899,
            'reminders' => [30, 15, 10, 7, 3, 2, 1],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */
    'currency'        => 'INR',
    'currency_symbol' => '₹',

    /*
    |--------------------------------------------------------------------------
    | "Expiring soon"
    |--------------------------------------------------------------------------
    |
    | When a subscription starts being shown amber in the admin list, and when
    | the app starts warning the farmer in place.
    |
    | Derived from each plan's FIRST reminder above, not fixed: a one-month plan
    | has 30 days on the day it is sold, so a flat 30-day window would paint it
    | amber — and tell the farmer it was about to run out — the moment they paid
    | for it. Warning starts when the plan itself says warning starts, which is
    | 7 days into a monthly plan and 30 into a six-month one.
    |
    | This value is only the fallback, used for a row whose plan key is no
    | longer in the catalogue.
    |
    */
    'expiring_soon_days' => 7,

];
