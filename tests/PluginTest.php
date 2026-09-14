<?php

/**
 * Plugin validation tests for Biometrics.
 *
 * Run with: ./vendor/bin/pest
 */
beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifestPath = $this->pluginPath.'/nativephp.json';
});

describe('Plugin Manifest', function () {
    it('has a valid nativephp.json file', function () {
        expect(file_exists($this->manifestPath))->toBeTrue();

        $content = file_get_contents($this->manifestPath);
        json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    });

    it('has required fields', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest)->toHaveKeys(['name', 'namespace', 'bridge_functions']);
        expect($manifest['name'])->toBe('smronju/nativephp-biometrics');
        expect($manifest['namespace'])->toBe('Biometrics');
    });

    it('has valid bridge functions', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['bridge_functions'])->toBeArray();

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function)->toHaveKeys(['name']);

            // At least one platform must implement it. (Not toHaveAnyKeys() — that
            // expectation does not exist in Pest 5, which is what runs this suite
            // when the plugin is developed inside the host app.)
            expect(array_intersect(['android', 'ios'], array_keys($function)))->not->toBeEmpty();
        }
    });

    it('declares support for both platforms — nativephp/mobile core registers Biometric.Prompt on neither', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['platforms'])->toBe(['android', 'ios']);

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function)->toHaveKeys(['android', 'ios']);
        }
    });

    it('declares the NSFaceIDUsageDescription Info.plist key Face ID requires', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['ios']['info_plist'])->toHaveKey('NSFaceIDUsageDescription');
        expect($manifest['ios']['info_plist']['NSFaceIDUsageDescription'])->toBeString()->not->toBeEmpty();
    });

    it('declares the one permission BiometricPrompt needs', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['android']['permissions'])->toBe(['android.permission.USE_BIOMETRIC']);
    });

    it('declares the androidx.biometric Gradle dependency', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['android']['dependencies']['implementation'])
            ->toContain('androidx.biometric:biometric:1.1.0');
    });

    it('has valid marketplace metadata', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        if (isset($manifest['keywords'])) {
            expect($manifest['keywords'])->toBeArray();
        }

        if (isset($manifest['category'])) {
            expect($manifest['category'])->toBeString();
        }

        if (isset($manifest['platforms'])) {
            expect($manifest['platforms'])->toBeArray();
            foreach ($manifest['platforms'] as $platform) {
                expect($platform)->toBeIn(['android', 'ios']);
            }
        }
    });
});

describe('Native Code', function () {
    it('has an Android Kotlin file registering Biometric.Prompt', function () {
        $kotlinFile = $this->pluginPath.'/resources/android/BiometricFunctions.kt';

        expect(file_exists($kotlinFile))->toBeTrue();

        $content = file_get_contents($kotlinFile);
        expect($content)->toContain('package com.iou.plugins.biometrics');
        expect($content)->toContain('object BiometricFunctions');
        expect($content)->toContain('class Prompt');
        expect($content)->toContain('BridgeFunction');
    });

    it('has matching bridge function classes in native code', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/BiometricFunctions.kt');
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/BiometricFunctions.swift');

        foreach ($manifest['bridge_functions'] as $function) {
            if (isset($function['android'])) {
                $parts = explode('.', $function['android']);
                $className = end($parts);
                expect($kotlinContent)->toContain("class {$className}");
            }

            if (isset($function['ios'])) {
                $parts = explode('.', $function['ios']);
                $className = end($parts);
                expect($swiftContent)->toContain("class {$className}");
            }
        }
    });

    it('blocks until a terminal outcome instead of returning immediately', function () {
        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/BiometricFunctions.kt');

        // Every other interactive plugin in this app (ContactsPicker, DocumentPicker)
        // returns immediately and reports later via an event, because
        // PendingBiometric::prompt() reads THIS call's own return value
        // synchronously — so this one has to actually wait.
        expect($kotlinContent)->toContain('CountDownLatch')
            ->and($kotlinContent)->toContain('latch.await');
    });

    it('does not treat a single rejected attempt as terminal', function () {
        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/BiometricFunctions.kt');

        // onAuthenticationFailed (one wrong finger) must NOT be overridden to release
        // the latch — only onAuthenticationSucceeded/onAuthenticationError are
        // terminal. The class may still mention it in a comment explaining why.
        expect($kotlinContent)->not->toContain('override fun onAuthenticationFailed');
    });

    it("returns the exact JSON shape PendingBiometric::prompt() decodes", function () {
        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/BiometricFunctions.kt');

        expect($kotlinContent)->toContain('"status" to')
            ->and($kotlinContent)->toContain('"success"')
            ->and($kotlinContent)->toContain('"failed"');
    });

    it('checks hardware/enrollment availability before showing a prompt', function () {
        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/BiometricFunctions.kt');

        expect($kotlinContent)->toContain('canAuthenticate')
            ->and($kotlinContent)->toContain('BIOMETRIC_SUCCESS');
    });

    it('shows help text on the prompt, not just a bare title', function () {
        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/BiometricFunctions.kt');

        // The title alone is just the host app's name (setTitle(label)) — a
        // subtitle/description is what actually tells the user what to do.
        expect($kotlinContent)->toContain('.setSubtitle(')
            ->and($kotlinContent)->toContain('.setDescription(');
    });
});

describe('iOS Native Code', function () {
    it('has a Swift file registering Biometric.Prompt', function () {
        $swiftFile = $this->pluginPath.'/resources/ios/BiometricFunctions.swift';

        expect(file_exists($swiftFile))->toBeTrue();

        $content = file_get_contents($swiftFile);
        expect($content)->toContain('enum BiometricFunctions');
        expect($content)->toContain('class Prompt');
        expect($content)->toContain('BridgeFunction');
    });

    it('blocks until a terminal outcome instead of returning immediately', function () {
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/BiometricFunctions.swift');

        // Same reasoning as the Android side: PendingBiometric::prompt() reads
        // THIS call's own return value synchronously, so evaluatePolicy's
        // callback has to be turned into a blocking wait.
        expect($swiftContent)->toContain('DispatchSemaphore')
            ->and($swiftContent)->toContain('semaphore.wait');
    });

    it("returns the exact JSON shape PendingBiometric::prompt() decodes", function () {
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/BiometricFunctions.swift');

        expect($swiftContent)->toContain('"status"')
            ->and($swiftContent)->toContain('"success"')
            ->and($swiftContent)->toContain('"failed"');
    });

    it('checks availability before showing a prompt', function () {
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/BiometricFunctions.swift');

        expect($swiftContent)->toContain('canEvaluatePolicy');
    });

    it('shows help text via localizedReason, not a bare system default', function () {
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/BiometricFunctions.swift');

        expect($swiftContent)->toContain('localizedReason');
    });

    it('disables the device-credential (passcode) fallback, matching the Android BIOMETRIC_STRONG-only contract', function () {
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/BiometricFunctions.swift');

        expect($swiftContent)->toContain('deviceOwnerAuthenticationWithBiometrics')
            ->and($swiftContent)->toContain('localizedFallbackTitle = ""');
    });
});

describe('PHP Classes', function () {
    it('has a service provider, and nothing else — this plugin reuses core PHP entirely', function () {
        $file = $this->pluginPath.'/src/BiometricsServiceProvider.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Smronju\NativephpBiometrics');
        expect($content)->toContain('class BiometricsServiceProvider');

        // No new facade/event classes: the whole point is reusing
        // Native\Mobile\Facades\Biometrics unchanged.
        expect(is_dir($this->pluginPath.'/src/Facades'))->toBeFalse();
        expect(is_dir($this->pluginPath.'/src/Events'))->toBeFalse();
    });

    it('parses without syntax errors', function () {
        $path = $this->pluginPath.'/src/BiometricsServiceProvider.php';

        expect(exec(sprintf('php -l %s 2>&1', escapeshellarg($path))))
            ->toContain('No syntax errors');
    });
});

describe('Composer Configuration', function () {
    it('has valid composer.json', function () {
        $composerPath = $this->pluginPath.'/composer.json';
        expect(file_exists($composerPath))->toBeTrue();

        $content = file_get_contents($composerPath);
        $composer = json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($composer['type'])->toBe('nativephp-plugin');
        expect($composer['extra']['nativephp']['manifest'])->toBe('nativephp.json');
    });
});
