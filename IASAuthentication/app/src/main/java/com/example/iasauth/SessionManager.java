package com.example.iasauth;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;

import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Random;
import java.util.UUID;

import io.jsonwebtoken.Claims;
import io.jsonwebtoken.Jwts;
import io.jsonwebtoken.SignatureAlgorithm;
import io.jsonwebtoken.security.Keys;

public class SessionManager {
    private static final String TAG = "SessionManager";
    private static final String PREF_NAME = "SessionData";
    private static final String KEY_JWT_TOKEN = "jwt_token";
    private static final String KEY_USER_EMAIL = "user_email";
    private static final String KEY_SESSION_ID = "session_id";
    private static final String KEY_LAST_ACTIVITY = "last_activity";
    private static final String KEY_BACKUP_CODES = "backup_codes";
    private static final int BACKUP_CODES_COUNT = 8;
    private static final int BACKUP_CODE_LENGTH = 8;

    private static final String JWT_SECRET = "your_secret_key_must_be_at_least_256_bits_long_for_hs256_algorithm_security"; // Updated secret key
    private static final long SESSION_EXPIRY = 24 * 60 * 60 * 1000; // 24 hours
    private static final long ACTIVITY_TIMEOUT = 30 * 60 * 1000; // 30 minutes

    private final SharedPreferences prefs;
    private final SharedPreferences.Editor editor;
    private final Context context;

    public SessionManager(Context context) {
        this.context = context.getApplicationContext(); // Use application context to prevent leaks
        this.prefs = this.context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        this.editor = prefs.edit();
    }

    public void createSession(String email) {
        try {
            if (email == null || email.isEmpty()) {
                Log.e(TAG, "Cannot create session with null or empty email");
                return;
            }

            String sessionId = UUID.randomUUID().toString();
            String jwtToken = generateJWT(email, sessionId);

            if (jwtToken != null) {
                editor.putString(KEY_JWT_TOKEN, jwtToken);
                editor.putString(KEY_USER_EMAIL, email);
                editor.putString(KEY_SESSION_ID, sessionId);
                editor.putLong(KEY_LAST_ACTIVITY, System.currentTimeMillis());
                editor.apply();
                Log.d(TAG, "Session created successfully for email: " + email);
            } else {
                Log.e(TAG, "Failed to generate JWT token");
            }
        } catch (Exception e) {
            Log.e(TAG, "Error creating session", e);
        }
    }

    private String generateJWT(String email, String sessionId) {
        try {
            Map<String, Object> claims = new HashMap<>();
            claims.put("email", email);
            claims.put("sessionId", sessionId);
            claims.put("createdAt", new Date());

            return Jwts.builder()
                    .setClaims(claims)
                    .setIssuedAt(new Date())
                    .setExpiration(new Date(System.currentTimeMillis() + SESSION_EXPIRY))
                    .signWith(Keys.hmacShaKeyFor(JWT_SECRET.getBytes()), SignatureAlgorithm.HS256)
                    .compact();
        } catch (Exception e) {
            Log.e(TAG, "Error generating JWT", e);
            return null;
        }
    }

    public boolean isLoggedIn() {
        try {
            String jwtToken = prefs.getString(KEY_JWT_TOKEN, null);
            if (jwtToken == null) {
                Log.d(TAG, "No JWT token found");
                return false;
            }

            Claims claims = Jwts.parserBuilder()
                    .setSigningKey(Keys.hmacShaKeyFor(JWT_SECRET.getBytes()))
                    .build()
                    .parseClaimsJws(jwtToken)
                    .getBody();

            Date expiration = claims.getExpiration();
            if (expiration.before(new Date())) {
                Log.d(TAG, "Session expired");
                logout();
                return false;
            }

            long lastActivity = prefs.getLong(KEY_LAST_ACTIVITY, 0);
            if (System.currentTimeMillis() - lastActivity > ACTIVITY_TIMEOUT) {
                Log.d(TAG, "Session timeout due to inactivity");
                logout();
                return false;
            }

            updateLastActivity();
            return true;
        } catch (Exception e) {
            Log.e(TAG, "Error checking login status", e);
            logout(); // Clear invalid session data
            return false;
        }
    }

    public String getSessionId() {
        try {
            return prefs.getString(KEY_SESSION_ID, null);
        } catch (Exception e) {
            Log.e(TAG, "Error getting session ID", e);
            return null;
        }
    }

    public String getUserEmail() {
        try {
            return prefs.getString(KEY_USER_EMAIL, null);
        } catch (Exception e) {
            Log.e(TAG, "Error getting user email", e);
            return null;
        }
    }

    public void updateLastActivity() {
        try {
            editor.putLong(KEY_LAST_ACTIVITY, System.currentTimeMillis());
            editor.apply();
        } catch (Exception e) {
            Log.e(TAG, "Error updating last activity", e);
        }
    }

    public List<String> generateBackupCodes() {
        try {
            List<String> backupCodes = new ArrayList<>();
            for (int i = 0; i < BACKUP_CODES_COUNT; i++) {
                String code = generateRandomCode();
                backupCodes.add(code);
            }
            saveBackupCodes(backupCodes);
            return backupCodes;
        } catch (Exception e) {
            Log.e(TAG, "Error generating backup codes", e);
            return new ArrayList<>();
        }
    }

    private String generateRandomCode() {
        String chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        StringBuilder code = new StringBuilder();
        Random random = new Random();
        for (int i = 0; i < BACKUP_CODE_LENGTH; i++) {
            code.append(chars.charAt(random.nextInt(chars.length())));
        }
        return code.toString();
    }

    public void saveBackupCodes(List<String> backupCodes) {
        try {
            String codesJson = new Gson().toJson(backupCodes);
            editor.putString(KEY_BACKUP_CODES, codesJson);
            editor.apply();
        } catch (Exception e) {
            Log.e(TAG, "Error saving backup codes", e);
        }
    }

    public List<String> getBackupCodes() {
        try {
            String codesJson = prefs.getString(KEY_BACKUP_CODES, null);
            if (codesJson != null) {
                Type type = new TypeToken<List<String>>(){}.getType();
                return new Gson().fromJson(codesJson, type);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error getting backup codes", e);
        }
        return new ArrayList<>();
    }

    public boolean validateBackupCode(String code) {
        try {
            List<String> backupCodes = getBackupCodes();
            if (backupCodes.contains(code)) {
                backupCodes.remove(code);
                saveBackupCodes(backupCodes);
                return true;
            }
        } catch (Exception e) {
            Log.e(TAG, "Error validating backup code", e);
        }
        return false;
    }

    public boolean hasBackupCodes() {
        try {
            return !getBackupCodes().isEmpty();
        } catch (Exception e) {
            Log.e(TAG, "Error checking backup codes", e);
            return false;
        }
    }

    public void logout() {
        try {
            editor.clear();
            editor.apply();
            Log.d(TAG, "Session cleared successfully");
        } catch (Exception e) {
            Log.e(TAG, "Error during logout", e);
        }
    }
} 