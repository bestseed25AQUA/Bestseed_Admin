# TRACKING FILTER REVERT — IMPORTANT

## What happened
On 2026-06-01, all GPS tracking distance filters were temporarily relaxed for testing.
They need to be reverted to original values after testing is done.

## Prompt for Claude (copy-paste this in new chat)

```
In the file:
E:\Flutter Project Techland\BestSeeds\Bestseeds-Admin\app\Http\Controllers\Api\Vendors_apis\DriverBookingController.php

Inside the method `updateDriverLocation()`, I temporarily changed 3 GPS tracking filters for testing. Please revert them to original values:

1. DISTANCE THRESHOLD (around line 597):
   - CURRENT: `$distFromLast >= 1`
   - REVERT TO: `$distFromLast >= 25`
   - Also change comment back to: "Save if: moved ≥ 25m from last point"
   - Remove the "TODO: REVERT TO 25m AFTER TESTING" comment

2. NET DISPLACEMENT (around line 571):
   - CURRENT: `if ($netDisplacement < 1)`
   - REVERT TO: `if ($netDisplacement < 30)`
   - Also change comment back to original
   - Remove the "TODO: REVERT TO 30m AFTER TESTING" comment

3. ZIG-ZAG REJECTION (around line 589):
   - CURRENT: `if ($distBackToOld < 0)`
   - REVERT TO: `if ($distBackToOld < 15)`
   - Remove the "TODO: REVERT TO 15m AFTER TESTING" comment

4. IS-MOVING CHECK (around line 518):
   - CURRENT: `$isMoving = $netDist > 1;`
   - REVERT TO: `$isMoving = $netDist > 30;`
   - Remove the "TODO: REVERT TO 30 AFTER TESTING" comment

After reverting, commit with message: "revert: restore tracking distance filters to production values (25m/30m/15m/30m)"
Then push to staging branch.

DO NOT change anything else in the file. Only these 4 values and their TODO comments.
```

## Original filter logic explained

### Filter 1: Distance from last saved point
```php
// Only save if driver moved 25+ meters from last tracking point
if ($distFromLast >= 25 && $secsSinceLast >= 10 && $isActuallyMoving && !$isZigZag) {
    $shouldSave = true;
}
```
WHY: GPS has ~15m natural noise. 25m threshold filters out noise while capturing real movement.

### Filter 2: Net displacement from 2-minute anchor
```php
// If driver hasn't moved 30m from where they were 2 minutes ago = stationary (GPS bounce)
if ($netDisplacement < 30) {
    $isActuallyMoving = false;
}
```
WHY: GPS bounces 10-25m even when phone is on a table. This catches "fake movement" where distance accumulates from noise but net displacement is zero.

### Filter 3: Zig-zag rejection
```php
// If new point is within 15m of 2-points-ago = bouncing back and forth = noise
if ($distBackToOld < 15) {
    $isZigZag = true;
}
```
WHY: GPS sometimes alternates between 2 positions 20m apart. Each hop passes the 25m check individually, but the pattern is A→B→A→B. This catches that pattern.

### Filter 4: Slow movement fallback (NOT CHANGED)
```php
// Even if only 10m moved, save if 2+ minutes passed
elseif ($distFromLast >= 10 && $secsSinceLast >= 120 && $isActuallyMoving && !$isZigZag) {
    $shouldSave = true;
}
```
WHY: In slow traffic (jams), driver may move only 10-20m in 2 minutes. Still save it so the route doesn't have gaps.
