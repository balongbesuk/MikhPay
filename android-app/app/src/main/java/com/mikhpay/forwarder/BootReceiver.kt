package com.mikhpay.forwarder

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action
        if (action == Intent.ACTION_BOOT_COMPLETED || action == "android.intent.action.QUICKBOOT_POWERON") {
            Log.d("MikhPayBoot", "Boot completed receiver triggered. Action: $action")
            
            // Try to start MainActivity on boot to guarantee the listener is active and registered
            try {
                val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)
                launchIntent?.let {
                    it.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    context.startActivity(it)
                    Log.d("MikhPayBoot", "Successfully launched MainActivity on boot.")
                }
            } catch (e: Exception) {
                Log.e("MikhPayBoot", "Failed to start MainActivity on boot: ${e.message}")
            }
        }
    }
}
