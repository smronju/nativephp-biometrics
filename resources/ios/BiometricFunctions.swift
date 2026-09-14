import Foundation
import LocalAuthentication

private let biometricDefaultEventClass = "Native\\Mobile\\Events\\Biometric\\Completed"
private let biometricAuthenticateTimeoutSeconds: Double = 60
private let biometricPromptReason = "Use Face ID or Touch ID to continue."

/// Namespace: "Biometric.*"
enum BiometricFunctions {

    /// Shows the system Face ID/Touch ID prompt and blocks the calling (PHP) thread
    /// until it reaches a terminal outcome.
    ///
    /// Mirrors `BiometricFunctions.Prompt` on Android exactly: `LAContext.evaluatePolicy`
    /// is callback-based, so this wraps it in a `DispatchSemaphore` the same way Android
    /// wraps `BiometricPrompt`'s callback in a `CountDownLatch` — both exist because
    /// `Native\Mobile\PendingBiometric::prompt(): bool` (nativephp/mobile core, unchanged)
    /// reads THIS call's own JSON return value synchronously, not a later event. Blocking
    /// here is safe: per nativephp/mobile's own architecture notes, the native call only
    /// blocks the PHP thread, never the UI thread, on iOS exactly as on Android. The
    /// bounded timeout below still guards against a stuck callback holding the PHP thread
    /// forever.
    class Prompt: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let id = parameters["id"] as? String
            let eventClass = (parameters["event"] as? String) ?? biometricDefaultEventClass

            let context = LAContext()

            // Empty string (not nil) hides the "Enter Password" fallback button
            // entirely, so this stays biometrics-only — matching Android's
            // BIOMETRIC_STRONG-only contract, with no device-credential fallback.
            context.localizedFallbackTitle = ""

            let policy = LAPolicy.deviceOwnerAuthenticationWithBiometrics

            var canEvaluateError: NSError?
            guard context.canEvaluatePolicy(policy, error: &canEvaluateError) else {
                dispatchCompleted(eventClass: eventClass, id: id, success: false)

                return BridgeResponse.success(data: ["status": "failed"])
            }

            let semaphore = DispatchSemaphore(value: 0)
            var success = false

            context.evaluatePolicy(policy, localizedReason: biometricPromptReason) { result, _ in
                success = result
                semaphore.signal()
            }

            _ = semaphore.wait(timeout: .now() + biometricAuthenticateTimeoutSeconds)

            dispatchCompleted(eventClass: eventClass, id: id, success: success)

            return BridgeResponse.success(data: ["status": success ? "success" : "failed"])
        }

        private func dispatchCompleted(eventClass: String, id: String?, success: Bool) {
            var payload: [String: Any] = ["success": success]
            if let id = id {
                payload["id"] = id
            }

            DispatchQueue.main.async {
                LaravelBridge.shared.send?(eventClass, payload)
            }
        }
    }
}
