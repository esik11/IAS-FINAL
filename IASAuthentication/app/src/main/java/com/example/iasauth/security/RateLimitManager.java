package com.example.iasauth.security;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;
import java.util.Calendar;

public class RateLimitManager {
    private static final String TAG = "RateLimitManager";
    private static final String PREF_NAME = "LoginAttempts";
    private static final int MAX_ATTEMPTS = 5;
    private static final int BLOCK_DURATION_MINUTES = 15;

    private final SharedPreferences prefs;

    public RateLimitManager(Context context) {
        prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    public boolean isRateLimited(String email) {
        int attempts = getAttempts(email);
        long blockTime = getBlockTime(email);
        long currentTime = System.currentTimeMillis();

        if (blockTime > currentTime) {
            long remainingMinutes = (blockTime - currentTime) / (60 * 1000);
            Log.d(TAG, "Account is rate limited. Try again in " + remainingMinutes + " minutes");
            return true;
        }

        return attempts >= MAX_ATTEMPTS;
    }

    public void recordLoginAttempt(String email, boolean success) {
        if (success) {
            // Reset attempts on successful login
            resetAttempts(email);
            return;
        }

        int attempts = getAttempts(email) + 1;
        prefs.edit()
            .putInt(getAttemptsKey(email), attempts)
            .apply();

        if (attempts >= MAX_ATTEMPTS) {
            setBlockTime(email);
        }
    }

    public int getRemainingAttempts(String email) {
        return Math.max(0, MAX_ATTEMPTS - getAttempts(email));
    }

    public long getBlockTimeRemaining(String email) {
        long blockTime = getBlockTime(email);
        long currentTime = System.currentTimeMillis();
        return Math.max(0, blockTime - currentTime);
    }

    private void setBlockTime(String email) {
        Calendar cal = Calendar.getInstance();
        cal.add(Calendar.MINUTE, BLOCK_DURATION_MINUTES);
        prefs.edit()
            .putLong(getBlockTimeKey(email), cal.getTimeInMillis())
            .apply();
    }

    private int getAttempts(String email) {
        return prefs.getInt(getAttemptsKey(email), 0);
    }

    private long getBlockTime(String email) {
        return prefs.getLong(getBlockTimeKey(email), 0);
    }

    private void resetAttempts(String email) {
        prefs.edit()
            .remove(getAttemptsKey(email))
            .remove(getBlockTimeKey(email))
            .apply();
    }

    private String getAttemptsKey(String email) {
        return "attempts_" + email.toLowerCase();
    }

    private String getBlockTimeKey(String email) {
        return "blockTime_" + email.toLowerCase();
    }
} 