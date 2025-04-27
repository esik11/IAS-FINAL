package com.example.iasauth;

import android.content.Intent;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.example.iasauth.dialogs.BackupCodesDialog;
import com.example.iasauth.security.PasswordValidator;
import com.google.android.material.textfield.TextInputLayout;
import com.google.firebase.auth.FirebaseAuth;
import com.google.firebase.auth.FirebaseUser;
import com.google.firebase.auth.UserProfileChangeRequest;

import java.util.List;

public class RegisterActivity extends AppCompatActivity {
    private EditText nameInput;
    private EditText emailInput;
    private EditText passwordInput;
    private EditText confirmPasswordInput;
    private TextInputLayout nameLayout;
    private TextInputLayout emailLayout;
    private TextInputLayout passwordLayout;
    private TextInputLayout confirmPasswordLayout;
    private Button registerButton;
    private TextView loginLink;
    private TextView messageBox;
    private ProgressBar loadingSpinner;
    private SessionManager sessionManager;
    private FirebaseAuth mAuth;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_register);

        sessionManager = new SessionManager(this);
        mAuth = FirebaseAuth.getInstance();

        // Initialize views
        nameInput = findViewById(R.id.nameInput);
        emailInput = findViewById(R.id.emailInput);
        passwordInput = findViewById(R.id.passwordInput);
        confirmPasswordInput = findViewById(R.id.confirmPasswordInput);
        nameLayout = findViewById(R.id.nameLayout);
        emailLayout = findViewById(R.id.emailLayout);
        passwordLayout = findViewById(R.id.passwordLayout);
        confirmPasswordLayout = findViewById(R.id.confirmPasswordLayout);
        registerButton = findViewById(R.id.registerButton);
        loginLink = findViewById(R.id.loginLink);
        messageBox = findViewById(R.id.messageBox);
        loadingSpinner = findViewById(R.id.loadingSpinner);

        // Set click listeners
        registerButton.setOnClickListener(v -> handleRegistration());
        loginLink.setOnClickListener(v -> {
            Intent intent = new Intent(RegisterActivity.this, LoginActivity.class);
            startActivity(intent);
            finish();
        });
    }

    private void handleRegistration() {
        // Reset errors
        nameLayout.setError(null);
        emailLayout.setError(null);
        passwordLayout.setError(null);
        confirmPasswordLayout.setError(null);
        messageBox.setVisibility(View.GONE);

        String name = nameInput.getText().toString().trim();
        String email = emailInput.getText().toString().trim();
        String password = passwordInput.getText().toString();
        String confirmPassword = confirmPasswordInput.getText().toString();

        // Validate input
        if (TextUtils.isEmpty(name)) {
            nameLayout.setError("Please enter your name");
            return;
        }

        if (TextUtils.isEmpty(email)) {
            emailLayout.setError("Please enter your email");
            return;
        }

        if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            emailLayout.setError("Please enter a valid email address");
            return;
        }

        // Validate password
        PasswordValidator.ValidationResult passwordValidation = PasswordValidator.validate(password);
        if (!passwordValidation.isValid) {
            StringBuilder errorMessage = new StringBuilder();
            for (String error : passwordValidation.errors) {
                errorMessage.append("• ").append(error).append("\n");
            }
            passwordLayout.setError(errorMessage.toString());
            return;
        }

        if (!password.equals(confirmPassword)) {
            confirmPasswordLayout.setError("Passwords do not match");
            return;
        }

        // Show loading state
        showLoading(true);

        // Create Firebase user
        mAuth.createUserWithEmailAndPassword(email, password)
                .addOnCompleteListener(this, task -> {
                    if (task.isSuccessful()) {
                        FirebaseUser user = mAuth.getCurrentUser();
                        if (user != null) {
                            // Update user profile with name
                            UserProfileChangeRequest profileUpdates = new UserProfileChangeRequest.Builder()
                                    .setDisplayName(name)
                                    .build();

                            user.updateProfile(profileUpdates)
                                    .addOnCompleteListener(profileTask -> {
                                        if (profileTask.isSuccessful()) {
                                            // Send email verification
                                            user.sendEmailVerification()
                                                    .addOnCompleteListener(verificationTask -> {
                                                        if (verificationTask.isSuccessful()) {
                                                            // Generate and show backup codes
                                                            List<String> backupCodes = sessionManager.generateBackupCodes();
                                                            BackupCodesDialog backupDialog = new BackupCodesDialog(this, backupCodes, () -> {
                                                                showMessage("Registration successful! Please verify your email.", false);
                                                                // Go to login activity
                                                                Intent intent = new Intent(RegisterActivity.this, LoginActivity.class);
                                                                intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
                                                                startActivity(intent);
                                                            });
                                                            backupDialog.show();
                                                        } else {
                                                            showError("Failed to send verification email.");
                                                        }
                                                        showLoading(false);
                                                    });
                                        } else {
                                            showError("Failed to update profile.");
                                            showLoading(false);
                                        }
                                    });
                        }
                    } else {
                        showError("Registration failed: " + task.getException().getMessage());
                        showLoading(false);
                    }
                });
    }

    private void showLoading(boolean show) {
        loadingSpinner.setVisibility(show ? View.VISIBLE : View.GONE);
        registerButton.setEnabled(!show);
        registerButton.setText(show ? "Registering..." : "Register");
    }

    private void showError(String message) {
        messageBox.setVisibility(View.VISIBLE);
        messageBox.setText(message);
        messageBox.setBackgroundResource(R.drawable.error_background);
    }

    private void showMessage(String message, boolean isError) {
        messageBox.setVisibility(View.VISIBLE);
        messageBox.setText(message);
        messageBox.setBackgroundResource(isError ? R.drawable.error_background : R.drawable.success_background);
    }
} 