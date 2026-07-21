# Phase K — Flutter / Firebase keep rules (release minify).
# Do not invent business obfuscation rules.

-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }
-dontwarn io.flutter.embedding.**

-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
-dontwarn com.google.firebase.**
-dontwarn com.google.android.gms.**

# flutter_secure_storage / local_auth reflective bits
-keep class com.it_nomads.fluttersecurestorage.** { *; }
-keep class androidx.biometric.** { *; }
