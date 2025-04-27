package com.example.iasauth;

import android.content.Context;
import android.content.SharedPreferences;
import java.util.Random;

public class OTPManager {
    private static final String PREF_NAME = "OTPData";
    private static final String KEY_OTP = "otp";
    private static final String KEY_EMAIL = "email";
    private static final String KEY_TIMESTAMP = "timestamp";
    private static final long OTP_EXPIRATION = 5 * 60 * 1000; // 5 minutes
    private static final int OTP_LENGTH = 6;

    private SharedPreferences prefs;
    private SharedPreferences.Editor editor;

    public OTPManager(Context context) {
        prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        editor = prefs.edit();
    }

    public String generateOTP() {
        Random random = new Random();
        StringBuilder otp = new StringBuilder();
        for (int i = 0; i < OTP_LENGTH; i++) {
            otp.append(random.nextInt(10));
        }
        return otp.toString();
    }

    public void storeOTP(String email, String otp) {
        editor.putString(KEY_OTP, otp);
        editor.putString(KEY_EMAIL, email);
        editor.putLong(KEY_TIMESTAMP, System.currentTimeMillis());
        editor.apply();
    }

    public boolean verifyOTP(String inputOTP) {
        String storedOTP = prefs.getString(KEY_OTP, null);
        long timestamp = prefs.getLong(KEY_TIMESTAMP, 0);
        
        if (storedOTP == null || timestamp == 0) {
            return false;
        }

        // Check if OTP is expired
        if (System.currentTimeMillis() - timestamp > OTP_EXPIRATION) {
            clearOTP();
            return false;
        }

        boolean isValid = inputOTP.equals(storedOTP);
        if (isValid) {
            clearOTP();
        }
        return isValid;
    }

    public void clearOTP() {
        editor.remove(KEY_OTP);
        editor.remove(KEY_EMAIL);
        editor.remove(KEY_TIMESTAMP);
        editor.apply();
    }

    public boolean isOTPExpired() {
        long timestamp = prefs.getLong(KEY_TIMESTAMP, 0);
        return System.currentTimeMillis() - timestamp > OTP_EXPIRATION;
    }

    public long getRemainingTime() {
        long timestamp = prefs.getLong(KEY_TIMESTAMP, 0);
        long elapsed = System.currentTimeMillis() - timestamp;
        return Math.max(0, OTP_EXPIRATION - elapsed);
    }
} 