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
            persistPendingUri(raw)
        }
    }

    private fun persistPendingUri(raw: String) {
        getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE)
            .edit()
            .putString("flutter.rateb_pending_activation_uri", raw)
            .apply()
    }

    private fun clearPersistedPendingUri() {
        getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE)
            .edit()
            .remove("flutter.rateb_pending_activation_uri")
            .apply()
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "consumePendingUri" -> {
                        val value = pendingActivationUri
                        pendingActivationUri = null
                        if (value == null) {
                            val prefs = getSharedPreferences(
                                "FlutterSharedPreferences",
                                Context.MODE_PRIVATE,
                            )
                            result.success(prefs.getString("flutter.rateb_pending_activation_uri", null))
                        } else {
                            result.success(value)
                        }
                        clearPersistedPendingUri()
                    }
                    else -> result.notImplemented()
                }
            }
    }
}
