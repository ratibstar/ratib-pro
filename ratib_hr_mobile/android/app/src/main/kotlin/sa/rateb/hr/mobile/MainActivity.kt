package sa.rateb.hr.mobile

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {

    companion object {
        private const val CHANNEL = "sa.rateb.hr.mobile/activation_intent"
        private const val PREFS = "rateb_activation_bridge"
        private const val KEY_URI = "pending_uri"

        @Volatile
        var pendingActivationUri: String? = null
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        captureIntent(intent)
        super.onCreate(savedInstanceState)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        captureIntent(intent)
    }

    private fun captureIntent(intent: Intent?) {
        if (intent?.action != Intent.ACTION_VIEW) {
            return
        }
        val uri: Uri = intent.data ?: return
        val raw = uri.toString()
        if (raw.contains("activate", ignoreCase = true)) {
            pendingActivationUri = raw
            bridgePrefs().edit().putString(KEY_URI, raw).apply()
        }
    }

    private fun bridgePrefs() =
        getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    private fun peekBridgedUri(): String? {
        val mem = pendingActivationUri
        if (!mem.isNullOrBlank()) {
            return mem
        }
        return bridgePrefs().getString(KEY_URI, null)
    }

    private fun clearBridgedUri() {
        pendingActivationUri = null
        bridgePrefs().edit().remove(KEY_URI).apply()
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "peekPendingUri" -> result.success(peekBridgedUri())
                    "clearPendingUri" -> {
                        clearBridgedUri()
                        result.success(null)
                    }
                    "consumePendingUri" -> {
                        val value = peekBridgedUri()
                        clearBridgedUri()
                        result.success(value)
                    }
                    else -> result.notImplemented()
                }
            }
    }
}
