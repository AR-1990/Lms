package com.pinnacle.pinnacle_school

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // The channel the server's pushes name (channel_id "school_alerts"); high importance shows them as pop-ups.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel("school_alerts", "School alerts", NotificationManager.IMPORTANCE_HIGH)
            channel.description = "Attendance, fees, results, diary and chat messages"
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
    }
}
