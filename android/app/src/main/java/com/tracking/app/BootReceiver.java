package com.tracking.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

/**
 * Restores foreground service lifecycle after reboot / app update when tracking was active.
 * JS remains source of truth for actual tracking behavior.
 */
public class BootReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        if (context == null || intent == null) return;
        String action = intent.getAction();
        if (!Intent.ACTION_BOOT_COMPLETED.equals(action)
            && !Intent.ACTION_MY_PACKAGE_REPLACED.equals(action)
            && !Intent.ACTION_LOCKED_BOOT_COMPLETED.equals(action)) {
            return;
        }

        if (!TrackingForegroundService.isTrackingActive(context)) {
            return;
        }

        TrackingForegroundService.startService(context);
    }
}
