# Changelog

All notable changes to this project will be documented in this file.

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
