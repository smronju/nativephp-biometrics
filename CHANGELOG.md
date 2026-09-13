# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-09-14

### Added

- `Biometric.Prompt` Android bridge function, backed by `androidx.biometric.BiometricPrompt`,
  filling the gap left by `nativephp/mobile` core (which only implements it on iOS).
- Blocks until a terminal outcome (success/error) and returns `{"status": "success"|"failed"}`,
  matching `Native\Mobile\PendingBiometric::prompt()`'s synchronous contract — no changes needed
  in consuming app code.
- Also dispatches the `Native\Mobile\Events\Biometric\Completed` event, for parity with the fluent
  `->completed()` callback.
