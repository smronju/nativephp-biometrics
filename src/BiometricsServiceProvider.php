<?php

namespace Smronju\NativephpBiometrics;

use Illuminate\Support\ServiceProvider;

/**
 * This plugin contributes no PHP runtime behavior of its own — it only supplies
 * the Android Kotlin bridge implementation for the `Biometric.Prompt` function
 * that `Native\Mobile\Facades\Biometrics` (nativephp/mobile core) already calls.
 * The provider exists only because nativephp.json requires one.
 */
class BiometricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}
