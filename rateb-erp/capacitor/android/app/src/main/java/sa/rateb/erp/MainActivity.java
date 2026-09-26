package sa.rateb.erp;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private static final String ACTIVATE_PAGE = "https://rateb.sa/rateb-erp/public/app-activate/";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // WebView cannot install files itself: hand downloads (app updates, exports) to the browser.
        getBridge().getWebView().setDownloadListener((url, userAgent, contentDisposition, mimetype, contentLength) -> {
            try {
                startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
            } catch (Exception ignored) {
                // No app can open the link.
            }
        });
        openActivationLink(getIntent());
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        openActivationLink(intent);
    }

    /** ratebapp://activate?code=ABCD-2345 → the company activation page, which opens the company panel. */
    private void openActivationLink(Intent intent) {
        Uri data = intent != null ? intent.getData() : null;
        if (data == null || !"ratebapp".equals(data.getScheme()) || !"activate".equals(data.getHost())) {
            return;
        }
        String code = data.getQueryParameter("code");
        if (code == null || !code.matches("[A-Za-z0-9-]{8,9}")) {
            return;
        }
        String url = ACTIVATE_PAGE + code + "?auto=1";
        getBridge().getWebView().post(() -> getBridge().getWebView().loadUrl(url));
    }
}
