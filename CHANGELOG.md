# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-09-14

### Added

- iOS implementation of `Biometric.Prompt`, backed by `LocalAuthentication` (`LAContext`) — Face ID
  or Touch ID, whichever the device has. `nativephp/mobile` core turns out **not** to implement
  this on iOS either (confirmed by reading the generated `BridgeFunctionRegistration.swift`: no
  `Biometric.*` category is registered there), correcting the plugin's original assumption that
  iOS "already worked" via core.
- Blocks on a `DispatchSemaphore` until a terminal outcome (success/error), mirroring the Android
  side's `CountDownLatch`, so `Native\Mobile\PendingBiometric::prompt()`'s synchronous contract
  holds on both platforms with no consuming-app code changes.
- Ships a default `NSFaceIDUsageDescription` Info.plist string via `ios.info_plist` (apps can
  override it in their own `config/nativephp.php`).
- No device-credential fallback on iOS either: `localizedFallbackTitle = ""` hides the "Enter
  Password" button, and `.deviceOwnerAuthenticationWithBiometrics` is biometrics-only — matching
  Android's `BIOMETRIC_STRONG`-only contract.

## [1.0.1] - 2026-09-14

### Added

- Subtitle and description text on the biometric prompt itself (`Confirm your identity` /
  `Use your fingerprint or face to continue.`), so the system dialog no longer shows just the app
  name with no explanation of what to do.

## [1.0.0] - 2026-09-14

### Added

- `Biometric.Prompt` Android bridge function, backed by `androidx.biometric.BiometricPrompt`,
  filling the gap left by `nativephp/mobile` core (which only implements it on iOS).
- Blocks until a terminal outcome (success/error) and returns `{"status": "success"|"failed"}`,
  matching `Native\Mobile\PendingBiometric::prompt()`'s synchronous contract — no changes needed
  in consuming app code.
- Also dispatches the `Native\Mobile\Events\Biometric\Completed` event, for parity with the fluent
  `->completed()` callback.
