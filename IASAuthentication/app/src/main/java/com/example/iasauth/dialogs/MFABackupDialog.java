package com.example.iasauth.dialogs;

import android.app.Dialog;
import android.content.Context;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.TextView;
import androidx.annotation.NonNull;
import com.example.iasauth.R;
import com.example.iasauth.security.BackupCodeManager;
import com.google.firebase.auth.FirebaseAuth;
import com.google.firebase.auth.FirebaseUser;

public class MFABackupDialog extends Dialog {
    private static final String TAG = "MFABackupDialog";
    private final Context context;
    private final String email;
    private final Runnable onSuccess;
    private EditText backupCodeInput;
    private TextView errorText;
    private ProgressBar progressBar;
    private Button verifyButton;
    private Button proceedToLoginButton;
    private Button cancelButton;
    private BackupCodeManager backupCodeManager;
    private Handler mainHandler;

    public MFABackupDialog(@NonNull Context context, String email, Runnable onSuccess) {
        super(context);
        this.context = context;
        this.email = email;
        this.onSuccess = onSuccess;
        this.backupCodeManager = new BackupCodeManager(context);
        this.mainHandler = new Handler(Looper.getMainLooper());
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        try {
            setContentView(R.layout.dialog_mfa_backup);
            setCanceledOnTouchOutside(false);

            // Initialize views
            initializeViews();
            setupClickListeners();

            // Set initial state
            proceedToLoginButton.setVisibility(View.GONE);
            errorText.setVisibility(View.GONE);
            progressBar.setVisibility(View.GONE);

        } catch (Exception e) {
            Log.e(TAG, "Error in onCreate", e);
            dismiss();
        }
    }

    private void initializeViews() {
        try {
            backupCodeInput = findViewById(R.id.backupCodeInput);
            errorText = findViewById(R.id.errorText);
            progressBar = findViewById(R.id.progressBar);
            verifyButton = findViewById(R.id.verifyButton);
            proceedToLoginButton = findViewById(R.id.proceedToLoginButton);
            cancelButton = findViewById(R.id.cancelButton);
        } catch (Exception e) {
            Log.e(TAG, "Error initializing views", e);
            throw e;
        }
    }

    private void setupClickListeners() {
        verifyButton.setOnClickListener(v -> verifyBackupCode());
        
        proceedToLoginButton.setOnClickListener(v -> {
            dismiss();
            mainHandler.post(() -> {
                try {
                    onSuccess.run();
                } catch (Exception e) {
                    Log.e(TAG, "Error in onSuccess callback", e);
                }
            });
        });
        
        cancelButton.setOnClickListener(v -> dismiss());
    }

    private void verifyBackupCode() {
        try {
            String code = backupCodeInput.getText().toString().trim();
            if (code.isEmpty()) {
                showError("Please enter a backup code");
                return;
            }

            showLoading(true);
            FirebaseUser user = FirebaseAuth.getInstance().getCurrentUser();
            if (user != null) {
                backupCodeManager.verifyBackupCode(user.getUid(), code, (isValid, message) -> {
                    mainHandler.post(() -> {
                        try {
                            if (isValid) {
                                // Show success state
                                showSuccess();
                            } else {
                                showError(message);
                            }
                            showLoading(false);
                        } catch (Exception e) {
                            Log.e(TAG, "Error handling backup code verification result", e);
                        }
                    });
                });
            } else {
                showError("User not authenticated");
                showLoading(false);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error verifying backup code", e);
            showError("An error occurred while verifying the backup code");
            showLoading(false);
        }
    }

    private void showSuccess() {
        try {
            errorText.setVisibility(View.GONE);
            verifyButton.setVisibility(View.GONE);
            backupCodeInput.setEnabled(false);
            proceedToLoginButton.setVisibility(View.VISIBLE);
            cancelButton.setVisibility(View.GONE);
        } catch (Exception e) {
            Log.e(TAG, "Error showing success state", e);
        }
    }

    private void showError(String message) {
        mainHandler.post(() -> {
            try {
                if (errorText != null) {
                    errorText.setText(message);
                    errorText.setVisibility(View.VISIBLE);
                }
            } catch (Exception e) {
                Log.e(TAG, "Error showing error message", e);
            }
        });
    }

    private void showLoading(boolean show) {
        mainHandler.post(() -> {
            try {
                if (progressBar != null && verifyButton != null && backupCodeInput != null) {
                    progressBar.setVisibility(show ? View.VISIBLE : View.GONE);
                    verifyButton.setEnabled(!show);
                    backupCodeInput.setEnabled(!show);
                }
            } catch (Exception e) {
                Log.e(TAG, "Error setting loading state", e);
            }
        });
    }
} 