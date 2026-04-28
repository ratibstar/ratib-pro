package com.tracking.app;

import android.Manifest;
import android.app.Activity;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.PowerManager;
import android.provider.Settings;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

@CapacitorPlugin(name = "TrackingForeground")
public class TrackingForegroundPlugin extends Plugin {

    private static final int REQ_POST_NOTIFICATIONS = 9901;

    @PluginMethod
    public void start(PluginCall call) {
        Context c = getContext();
        if (c == null) {
            call.reject("No context");
            return;
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(c, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                Activity a = getActivity();
                if (a != null) {
                    ActivityCompat.requestPermissions(a, new String[]{Manifest.permission.POST_NOTIFICATIONS}, REQ_POST_NOTIFICATIONS);
                }
            }
        }
        TrackingForegroundService.setTrackingActive(c, true);
        TrackingForegroundService.startService(c);
        call.resolve();
    }

    @PluginMethod
    public void stop(PluginCall call) {
        Context c = getContext();
        if (c == null) {
            call.reject("No context");
            return;
        }
        TrackingForegroundService.setTrackingActive(c, false);
        TrackingForegroundService.stopService(c);
        call.resolve();
    }

    @PluginMethod
    public void getBatteryStatus(PluginCall call) {
        Context c = getContext();
        if (c == null) {
            call.reject("No context");
            return;
        }
        boolean ignoring = isIgnoringBatteryOptimizations(c);
        JSObject ret = new JSObject();
        ret.put("isIgnoringBatteryOptimizations", ignoring);
        call.resolve(ret);
    }

    @PluginMethod
    public void openAppBatterySettings(PluginCall call) {
        Context c = getContext();
        if (c == null) {
            call.reject("No context");
            return;
        }
        try {
            Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
            intent.setData(Uri.parse("package:" + c.getPackageName()));
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            c.startActivity(intent);
        } catch (Exception e) {
            call.reject("Unable to open settings", e);
            return;
        }
        call.resolve();
    }

    @PluginMethod
    public void getDeviceManufacturer(PluginCall call) {
        JSObject ret = new JSObject();
        ret.put("manufacturer", Build.MANUFACTURER != null ? Build.MANUFACTURER : "");
        call.resolve(ret);
    }

    @PluginMethod
    public void openBatteryOptimizationSettings(PluginCall call) {
        Context c = getContext();
        if (c == null) {
            call.reject("No context");
            return;
        }
        try {
            Intent intent = new Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            c.startActivity(intent);
            call.resolve();
        } catch (Exception e) {
            call.reject("Unable to open battery optimization settings", e);
        }
    }

    @PluginMethod
    public void openAppSettings(PluginCall call) {
        Context c = getContext();
        if (c == null) {
            call.reject("No context");
            return;
        }
        try {
            Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
            intent.setData(Uri.parse("package:" + c.getPackageName()));
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            c.startActivity(intent);
            call.resolve();
        } catch (Exception e) {
            call.reject("Unable to open app settings", e);
        }
    }

    @PluginMethod
    public void openAutoStartSettings(PluginCall call) {
        Context c = getContext();
        if (c == null) {
            call.reject("No context");
            return;
        }
        String m = Build.MANUFACTURER != null ? Build.MANUFACTURER.toLowerCase() : "";
        try {
            Intent i = null;
            if (m.contains("xiaomi") || m.contains("redmi") || m.contains("poco")) {
                i = new Intent();
                i.setComponent(new ComponentName(
                    "com.miui.securitycenter",
                    "com.miui.permcenter.autostart.AutoStartManagementActivity"
                ));
            } else if (m.contains("huawei") || m.contains("honor")) {
                i = new Intent();
                i.setComponent(new ComponentName(
                    "com.huawei.systemmanager",
                    "com.huawei.systemmanager.optimize.process.ProtectActivity"
                ));
            } else if (m.contains("oppo") || m.contains("realme") || m.contains("oneplus")) {
                i = new Intent();
                i.setComponent(new ComponentName(
                    "com.coloros.safecenter",
                    "com.coloros.safecenter.permission.startup.StartupAppListActivity"
                ));
            }
            if (i != null) {
                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                c.startActivity(i);
                call.resolve();
                return;
            }
        } catch (Exception ignored) {
            // Fall through to generic app settings.
        }
        openAppSettings(call);
    }

    private static boolean isIgnoringBatteryOptimizations(Context c) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
            return true;
        }
        PowerManager pm = (PowerManager) c.getSystemService(Context.POWER_SERVICE);
        if (pm == null) {
            return true;
        }
        return pm.isIgnoringBatteryOptimizations(c.getPackageName());
    }
}
