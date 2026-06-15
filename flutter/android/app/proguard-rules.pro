-dontwarn org.bouncycastle.jsse.**
-dontwarn org.conscrypt.**
-dontwarn org.openjsse.**

# mobile_scanner / Google ML Kit Barcode (bundled: com.google.mlkit:barcode-scanning).
# R8 ở release build strip các class native + reflection của ML Kit khiến camera
# mở nhưng không quét được. Giữ lại ML Kit, CameraX và các entry point liên quan.
-keep class com.google.mlkit.** { *; }
-keep class com.google.android.gms.vision.** { *; }
-keep class com.google.android.gms.internal.mlkit_vision_** { *; }
-dontwarn com.google.mlkit.**

# ML Kit nạp model barcode qua reflection theo tên class trong manifest.
-keep class com.google.android.gms.internal.** { *; }
-keep class com.google.android.gms.common.** { *; }

# AndroidX CameraX (mobile_scanner dùng camera-camera2 + camera-lifecycle).
-keep class androidx.camera.** { *; }
-dontwarn androidx.camera.**