package com.iou.plugins.biometrics

import androidx.biometric.BiometricManager
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_STRONG
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * Namespace: "Biometric.*"
 */
object BiometricFunctions {

    private const val DEFAULT_EVENT_CLASS = "Native\\Mobile\\Events\\Biometric\\Completed"
    private const val AUTHENTICATE_TIMEOUT_SECONDS = 60L

    /**
     * Shows the system fingerprint/face prompt and blocks the calling (PHP) thread
     * until it reaches a terminal outcome.
     *
     * Every other interactive bridge function in this app's plugins (ContactsPicker,
     * DocumentPicker, Camera, Dialog) returns immediately and reports its result
     * later via a dispatched event — but `Native\Mobile\PendingBiometric::prompt()`
     * (nativephp/mobile core, unchanged) reads THIS call's own JSON return value
     * synchronously (`$decoded['status'] === 'success'`), not an event. Blocking
     * here is safe: per nativephp/mobile's own architecture notes, the native call
     * only blocks the PHP thread, never the UI thread, and — unlike an external
     * Camera activity — a BiometricPrompt is a dialog over the CURRENT activity,
     * so the app is never backgrounded while it's showing. The bounded timeout
     * below still guards against a stuck callback holding the PHP thread forever.
     */
    class Prompt(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            val eventClass = (parameters["event"] as? String) ?: DEFAULT_EVENT_CLASS

            val canAuthenticate = BiometricManager.from(activity).canAuthenticate(BIOMETRIC_STRONG)

            if (canAuthenticate != BiometricManager.BIOMETRIC_SUCCESS) {
                dispatchCompleted(eventClass, id, success = false)

                return mapOf("status" to "failed")
            }

            val latch = CountDownLatch(1)
            var success = false

            activity.runOnUiThread {
                val executor = ContextCompat.getMainExecutor(activity)
                val callback = object : BiometricPrompt.AuthenticationCallback() {
                    override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                        success = true
                        latch.countDown()
                    }

                    override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                        success = false
                        latch.countDown()
                    }

                    // Deliberately no override for onAuthenticationFailed: a single
                    // rejected fingerprint/face isn't terminal — the system prompt
                    // stays open for another attempt, so the latch must not release
                    // yet.
                }

                val label = activity.applicationInfo.loadLabel(activity.packageManager)

                val promptInfo = BiometricPrompt.PromptInfo.Builder()
                    .setTitle(label)
                    .setNegativeButtonText("Cancel")
                    .setAllowedAuthenticators(BIOMETRIC_STRONG)
                    .build()

                BiometricPrompt(activity, executor, callback).authenticate(promptInfo)
            }

            latch.await(AUTHENTICATE_TIMEOUT_SECONDS, TimeUnit.SECONDS)

            dispatchCompleted(eventClass, id, success)

            return mapOf("status" to if (success) "success" else "failed")
        }

        private fun dispatchCompleted(eventClass: String, id: String?, success: Boolean) {
            val payload = JSONObject().apply {
                put("success", success)
                if (id != null) {
                    put("id", id)
                }
            }

            NativeActionCoordinator.dispatchEvent(activity, eventClass, payload.toString())
        }
    }
}
