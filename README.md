# NativePHP Biometrics (Android)

Adds the missing **Android** implementation for [NativePHP Mobile](https://nativephp.com)'s
`Biometrics::prompt()`. `nativephp/mobile` core already implements this on iOS (Face ID / Touch
ID) — on Android, `Biometric.Prompt` isn't registered at all, so `Biometrics::prompt()->prompt()`
always returns `false`, regardless of the device's hardware or enrolled fingerprints.

This plugin registers the same `Biometric.Prompt` bridge function on Android, backed by
`androidx.biometric.BiometricPrompt`. **You don't call anything new** — your existing
`Native\Mobile\Facades\Biometrics` code starts working on Android once this plugin is installed
and registered.

This plugin is **Android-only**. Nothing to install for iOS — it already works out of the box via
`nativephp/mobile` core.

## Requirements

- `nativephp/mobile` ^4.1
- Android API 23+

## Installation

```bash
composer require smronju/nativephp-biometrics
php artisan vendor:publish --tag=nativephp-plugins-provider
php artisan native:plugin:register smronju/nativephp-biometrics
```

Then rebuild: `php artisan native:run android`.

## Usage

Nothing changes in your PHP code — use the facade nativephp/mobile core already ships:

```php
use Native\Mobile\Facades\Biometrics;

if (Biometrics::prompt()->prompt()) {
    // authenticated
}
```

`prompt(): bool` blocks until the system prompt reaches a terminal outcome (success, error, or
cancel) and returns the real result directly — the same synchronous contract the iOS
implementation already has. A fluent, event-based alternative is also available if you prefer it:

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
since an external picker/camera Activity can background the app indefinitely. A `BiometricPrompt`
is different: it's a dialog over the *current* activity, so the app is never backgrounded while
it's showing, and Android auto-cancels the prompt if the activity is paused — so this plugin blocks
`execute()` on a bounded `CountDownLatch` (60s) until `BiometricPrompt`'s own callback resolves,
matching what `Native\Mobile\PendingBiometric::prompt()` already expects as its return value.

## What this plugin does NOT do

- It doesn't add a device-credential (PIN/pattern) fallback — only `BIOMETRIC_STRONG` biometrics.
- It doesn't pre-check availability without showing a prompt — `BiometricManager.canAuthenticate()`
  is checked internally, and a device with no hardware or no enrolled biometric simply gets
  `{"status": "failed"}` back with no prompt shown at all.

## License

MIT
