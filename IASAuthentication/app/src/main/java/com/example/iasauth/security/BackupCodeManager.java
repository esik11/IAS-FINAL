package com.example.iasauth.security;

import android.content.Context;
import android.content.SharedPreferences;
import com.google.firebase.database.DatabaseReference;
import com.google.firebase.database.FirebaseDatabase;
import java.util.HashMap;
import java.util.Map;

public class BackupCodeManager {
    private static final String PREF_NAME = "BackupCodes";
    private final SharedPreferences prefs;
    private final DatabaseReference dbRef;

    public BackupCodeManager(Context context) {
        prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        dbRef = FirebaseDatabase.getInstance().getReference("backup_codes");
    }

    public void storeBackupCodes(String userId, String email, String[] codes) {
        Map<String, Object> backupCodesMap = new HashMap<>();
        for (String code : codes) {
            Map<String, Object> codeData = new HashMap<>();
            codeData.put("code", code);
            codeData.put("is_used", false);
            codeData.put("created_at", System.currentTimeMillis());
            backupCodesMap.put(code, codeData);
        }

        dbRef.child(userId).setValue(backupCodesMap)
            .addOnSuccessListener(aVoid -> {
                // Store codes locally for offline access
                SharedPreferences.Editor editor = prefs.edit();
                editor.putString("codes_" + email, String.join(",", codes));
                editor.apply();
            });
    }

    public interface VerificationCallback {
        void onResult(boolean isValid, String message);
    }

    public void verifyBackupCode(String userId, String code, VerificationCallback callback) {
        dbRef.child(userId).child(code).get()
            .addOnCompleteListener(task -> {
                if (task.isSuccessful() && task.getResult() != null) {
                    Map<String, Object> codeData = (Map<String, Object>) task.getResult().getValue();
                    
                    if (codeData == null) {
                        callback.onResult(false, "Invalid backup code");
                        return;
                    }

                    boolean isUsed = (boolean) codeData.getOrDefault("is_used", false);
                    if (isUsed) {
                        callback.onResult(false, "Backup code has already been used");
                        return;
                    }

                    // Mark code as used
                    Map<String, Object> updates = new HashMap<>();
                    updates.put("is_used", true);
                    updates.put("used_at", System.currentTimeMillis());
                    
                    dbRef.child(userId).child(code).updateChildren(updates)
                        .addOnSuccessListener(aVoid -> callback.onResult(true, "Backup code verified successfully"))
                        .addOnFailureListener(e -> callback.onResult(false, "Failed to update backup code status"));
                } else {
                    callback.onResult(false, "Failed to verify backup code");
                }
            });
    }

    public boolean hasStoredBackupCodes(String email) {
        return prefs.contains("codes_" + email);
    }

    public String[] getStoredBackupCodes(String email) {
        String codes = prefs.getString("codes_" + email, "");
        return codes.isEmpty() ? new String[0] : codes.split(",");
    }

    public void clearStoredBackupCodes(String email) {
        prefs.edit().remove("codes_" + email).apply();
    }
} 