package com.example.iasauth.security;

import java.util.ArrayList;
import java.util.List;
import java.util.regex.Pattern;

public class PasswordValidator {
    private static final int MIN_LENGTH = 8;
    private static final Pattern HAS_UPPERCASE = Pattern.compile("[A-Z]");
    private static final Pattern HAS_LOWERCASE = Pattern.compile("[a-z]");
    private static final Pattern HAS_NUMBER = Pattern.compile("\\d");
    private static final Pattern HAS_SPECIAL = Pattern.compile("[^A-Za-z0-9]");

    public static class ValidationResult {
        public final boolean isValid;
        public final List<String> errors;

        private ValidationResult(boolean isValid, List<String> errors) {
            this.isValid = isValid;
            this.errors = errors;
        }
    }

    public static ValidationResult validate(String password) {
        List<String> errors = new ArrayList<>();

        if (password.length() < MIN_LENGTH) {
            errors.add("Password must be at least 8 characters long");
        }

        if (!HAS_UPPERCASE.matcher(password).find()) {
            errors.add("Password must contain at least one uppercase letter");
        }

        if (!HAS_LOWERCASE.matcher(password).find()) {
            errors.add("Password must contain at least one lowercase letter");
        }

        if (!HAS_NUMBER.matcher(password).find()) {
            errors.add("Password must contain at least one number");
        }

        if (!HAS_SPECIAL.matcher(password).find()) {
            errors.add("Password must contain at least one special character");
        }

        return new ValidationResult(errors.isEmpty(), errors);
    }

    public static boolean isValid(String password) {
        return validate(password).isValid;
    }
} 