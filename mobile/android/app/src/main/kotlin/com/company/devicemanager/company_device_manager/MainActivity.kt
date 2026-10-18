package com.company.devicemanager.company_device_manager

import android.content.Context
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.engine.FlutterEngineCache

/**
 * Caches the FlutterEngine so Dart keeps running after Recents destroys the
 * Activity. Live audio / location also run in the FGS TaskHandler isolate;
 * keeping the UI engine helps when the Activity is only backgrounded.
 *
 * Note: hot restart can fail with a cached engine — use full Stop + flutter run.
 */
class MainActivity : FlutterActivity() {
    companion object {
        const val ENGINE_ID = "cdm_cached_engine"
    }

    override fun provideFlutterEngine(context: Context): FlutterEngine? {
        return FlutterEngineCache.getInstance().get(ENGINE_ID)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        FlutterEngineCache.getInstance().put(ENGINE_ID, flutterEngine)
    }

    override fun shouldDestroyEngineWithHost(): Boolean = false
}
