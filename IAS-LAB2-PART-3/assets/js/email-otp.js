import { getAuth, sendSignInLinkToEmail } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";

// Function to generate a 6-digit OTP
function generateOTP() {
    return Math.floor(100000 + Math.random() * 900000).toString();
}

// Function to store OTP with 1-minute expiration
function storeOTP(otp, email) {
    const expirationTime = new Date().getTime() + 60000; // 1 minute from now
    const otpData = {
        code: otp,
        email: email,
        expiration: expirationTime
    };
    sessionStorage.setItem('otpData', JSON.stringify(otpData));
}

// Function to verify OTP
function verifyOTP(inputOTP) {
    const otpData = JSON.parse(sessionStorage.getItem('otpData'));
    if (!otpData) return { valid: false, message: 'No OTP found' };

    const currentTime = new Date().getTime();
    if (currentTime > otpData.expiration) {
        return { valid: false, message: 'OTP has expired' };
    }

    if (inputOTP === otpData.code) {
        sessionStorage.removeItem('otpData'); // Clear OTP after successful verification
        return { valid: true, message: 'OTP verified successfully' };
    }

    return { valid: false, message: 'Invalid OTP' };
}

// Function to send OTP via email
async function sendOTP(email, otp) {
    if (!otp) {
        otp = generateOTP();
    }
    
    console.log(`Attempting to send OTP to ${email}: ${otp}`);
    
    try {
        // Use absolute path from domain root
        const response = await fetch('/IAS-LAB2-PART-3/app/api/send-otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                otp: otp
            })
        });

        console.log(`Server responded with status: ${response.status}`);
        
        // Get the raw text first to see what we're dealing with
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        // Try to parse as JSON, if possible
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Parsed JSON response:', data);
            
            if (data.success) {
                console.log('OTP sent successfully to email');
                storeOTP(otp, email);
                return true;
            } else {
                console.error('Server error:', data.message);
                
                // For development purposes only - still allow login flow for testing
                // Remove this in production
                if (window.location.hostname === 'localhost') {
                    console.warn('DEV MODE: Proceeding despite email error');
                    storeOTP(otp, email);
                    return true;
                }
                
                return false;
            }
        } catch (jsonError) {
            console.error('Response is not valid JSON:', responseText);
            
            // For development purposes only - still allow login flow for testing
            // Remove this in production
            if (window.location.hostname === 'localhost') {
                console.warn('DEV MODE: Proceeding despite server error');
                storeOTP(otp, email);
                return true;
            }
            
            return false;
        }
        
    } catch (error) {
        console.error('Error sending OTP:', error);
        
        // For development purposes only - still allow login flow for testing
        // Remove this in production
        if (window.location.hostname === 'localhost') {
            console.warn('DEV MODE: Proceeding despite network error');
            storeOTP(otp, email);
            return true;
        }
        
        return false;
    }
}

// Function to start OTP timer
function startOTPTimer(timerElement, resendButton) {
    let timeLeft = 60; // 1 minute in seconds
    resendButton.disabled = true;

    const timer = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timer);
            timerElement.textContent = 'OTP expired';
            resendButton.disabled = false;
            return;
        }

        timerElement.textContent = `Time remaining: ${timeLeft} seconds`;
        timeLeft--;
    }, 1000);

    return timer;
}

export { generateOTP, storeOTP, verifyOTP, sendOTP, startOTPTimer }; 