package com.tracking.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Binder;
import android.os.Build;
import android.os.IBinder;
import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

/**
 * Foreground service only — keeps the process prioritized while tracking is on.
 * JS remains the source of truth for location queue/sync logic.
 */
public class TrackingForegroundService extends Service {

    public static final String ACTION_START = "com.tracking.app.trackingfg.START";
    public static final String ACTION_STOP = "com.tracking.app.trackingfg.STOP";

    private static final String CHANNEL_ID = "tracking_foreground_v1";
    private static final int NOTIFICATION_ID = 91001;
    private static final String PREFS_NAME = "tracking_foreground_prefs";
    private static final String KEY_TRACKING_ACTIVE = "tracking_active";

    private final IBinder binder = new LocalBinder();

    public static class LocalBinder extends Binder {
        LocalBinder() {
            super();
        }
    }

    @Override
    public void onCreate() {
        super.onCreate();
        createChannelIfNeeded();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent != null && ACTION_STOP.equals(intent.getAction())) {
            setTrackingActive(this, false);
            stopForeground(STOP_FOREGROUND_REMOVE);
            stopSelf();
            return START_NOT_STICKY;
        }
        if (!isTrackingActive(this)) {
            stopSelf();
            return START_NOT_STICKY;
        }
        startForeground(NOTIFICATION_ID, buildNotification());
        return START_STICKY;
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return binder;
    }

    public static void setTrackingActive(Context context, boolean isActive) {
        if (context == null) return;
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        prefs.edit().putBoolean(KEY_TRACKING_ACTIVE, isActive).apply();
    }

    public static boolean isTrackingActive(Context context) {
        if (context == null) return false;
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        return prefs.getBoolean(KEY_TRACKING_ACTIVE, false);
    }

    public static void startService(Context context) {
        if (context == null) return;
        Intent i = new Intent(context, TrackingForegroundService.class);
        i.setAction(ACTION_START);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            context.startForegroundService(i);
        } else {
            context.startService(i);
        }
    }

    public static void stopService(Context context) {
        if (context == null) return;
        Intent i = new Intent(context, TrackingForegroundService.class);
        i.setAction(ACTION_STOP);
        context.startService(i);
    }

    private void createChannelIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }
        NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (nm == null) {
            return;
        }
        NotificationChannel channel = new NotificationChannel(
            CHANNEL_ID,
            "Location tracking",
            NotificationManager.IMPORTANCE_LOW
        );
        channel.setDescription("Shown while worker tracking is active");
        channel.setLockscreenVisibility(Notification.VISIBILITY_PUBLIC);
        nm.createNotificationChannel(channel);
    }

    private Notification buildNotification() {
        Intent launch = new Intent(this, MainActivity.class);
        launch.setFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent pi = PendingIntent.getActivity(this, 0, launch, flags);

        int smallIcon = getApplicationInfo().icon;
        if (smallIcon == 0) {
            smallIcon = android.R.drawable.stat_sys_gps;
        }

        return new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Tracking active")
            .setContentText("Location tracking is running")
            .setSmallIcon(smallIcon)
            .setContentIntent(pi)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setCategory(Notification.CATEGORY_SERVICE)
            .build();
    }
}
