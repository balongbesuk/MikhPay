# Add project specific ProGuard rules here.
# By default, the noise in this file is disabled.

# Keep OkHttp rules if needed
-keepattributes Signature, *Annotation*, InnerClasses
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn javax.annotation.**
-dontwarn org.conscrypt.**
# Keep our listener classes
-keep class com.mikhpay.forwarder.MikhPayListenerService { *; }
