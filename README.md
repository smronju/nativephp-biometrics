# NativePHP Biometrics

Implements [NativePHP Mobile](https://nativephp.com)'s `Biometrics::prompt()` on **both**
platforms. `nativephp/mobile` core ships the PHP-side `Biometrics`/`PendingBiometric` classes but
registers no native `Biometric.Prompt` bridge function on either Android or iOS — calling
`Biometrics::prompt()->prompt()` without this plugin always returns `false`, regardless of the
device's hardware or enrolled biometrics.

This plugin registers `Biometric.Prompt` on:

- **Android**, backed by `androidx.biometric.BiometricPrompt`.
- **iOS**, backed by `LocalAuthentication` (`LAContext`) — Face ID or Touch ID, whichever the
  device has.

**You don't call anything new** — your existing `Native\Mobile\Facades\Biometrics` code starts
working on both platforms once this plugin is installed and registered.

## Requirements

- `nativephp/mobile` ^4.1
- Android API 23+
- iOS 15+

## Installation

```bash
composer require smronju/nativephp-biometrics
php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register smronju/nativephp-biometrics
```

Then rebuild: `php artisan native:run android` or `php artisan native:run ios`.

iOS requires an `NSFaceIDUsageDescription` Info.plist string (Face ID prompts the user for this
permission the first time it's used). This plugin ships a default; override it in your app's
`config/nativephp.php` under `permissions.NSFaceIDUsageDescription` if you want custom wording.

## Usage

Nothing changes in your PHP code — use the facade nativephp/mobile core already ships:

```php
use Native\Mobile\Facades\Biometrics;

if (Biometrics::prompt()->prompt()) {
    // authenticated
}
```

`prompt(): bool` blocks until the system prompt reaches a terminal outcome (success, error, or
cancel) and returns the real result directly. A fluent, event-based alternative is also available
if you prefer it:

```php
use Native\Mobile\Events\Biometric\Completed;

Biometrics::prompt()->completed(function (Completed $event) {
    if ($event->success) {
        // authenticated
    }
});
```

## Why blocking is safe here

Every other interactive NativePHP bridge function (camera, dialogs, pickers) returns immediately
and reports its result later via a dispatched event — because native calls only block the *PHP*
thread, never the UI thread, blocking is architecturally safe, but it's usually avoided anyway
since an external picker/camera Activity/view controller can background the app indefinitely. A
biometric prompt is different: it's a dialog over the *current* screen, so the app is never
backgrounded while it's showing. On Android, `execute()` blocks on a bounded `CountDownLatch` (60s)
until `BiometricPrompt`'s own callback resolves; on iOS, it blocks on a `DispatchSemaphore` (also
60s) until `LAContext.evaluatePolicy`'s completion handler fires. Both match what
`Native\Mobile\PendingBiometric::prompt()` already expects as its return value.

## What this plugin does NOT do

- It doesn't add a device-credential (PIN/pattern/passcode) fallback — biometrics only. Android
  requests `BIOMETRIC_STRONG`; iOS hides the "Enter Password" fallback button
  (`localizedFallbackTitle = ""`) and uses `.deviceOwnerAuthenticationWithBiometrics`.
- It doesn't pre-check availability without showing a prompt on success — availability IS checked
  internally before ever presenting a prompt (`BiometricManager.canAuthenticate()` on Android,
  `LAContext.canEvaluatePolicy()` on iOS), and a device with no hardware or no enrolled biometric
  simply gets `{"status": "failed"}` back with no prompt shown at all.

## License

MIT
