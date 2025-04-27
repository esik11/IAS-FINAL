# IAS-LAB2-PART-3 Project Setup Guide

## Prerequisites
- XAMPP (with Apache and PHP)
- Git
- Web Browser (Chrome/Firefox recommended)

## Installation Steps

### 1. Clone the Repository
```bash
# Navigate to XAMPP's htdocs directory
cd C:\xampp\htdocs

# Clone the repository
git clone [your-repository-url] IAS-FINAL
```

### 2. Configure SSL Certificate
The project uses HTTPS for secure communication. Follow these steps to set up SSL:

1. **Generate SSL Certificate**:
   - Open Command Prompt as Administrator
   - Run these commands:
   ```cmd
   cd C:\xampp\apache\bin
   openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout ..\conf\ssl.key\server.key -out ..\conf\ssl.crt\server.crt -config ..\..\htdocs\IAS-FINAL\IAS-LAB2-PART-3\openssl.cnf -extensions v3_req
   ```

2. **Install the Certificate**:
   - Navigate to `C:\xampp\apache\conf\ssl.crt`
   - Right-click on `server.crt`
   - Select "Install Certificate"
   - Choose "Local Machine"
   - Click "Next"
   - Select "Place all certificates in the following store"
   - Click "Browse"
   - Select "Trusted Root Certification Authorities"
   - Click "OK"
   - Complete the installation

### 3. Configure Apache
1. Open `C:\xampp\apache\conf\httpd.conf`
2. Make sure these lines are uncommented (remove # if present):
   ```apache
   LoadModule ssl_module modules/mod_ssl.so
   LoadModule rewrite_module modules/mod_rewrite.so
   Include conf/extra/httpd-ssl.conf
   ```

### 4. Start the Server
1. Open XAMPP Control Panel
2. Stop Apache if it's running
3. Start Apache
4. Clear your browser cache
5. Open a new browser window

### 5. Access the Application
- Open your browser
- Go to `https://localhost/IAS-FINAL/IAS-LAB2-PART-3/`
- You should see a secure lock icon in your browser

## Troubleshooting

### SSL Certificate Issues
If you see certificate warnings:
1. Make sure you followed all certificate installation steps
2. Clear your browser cache
3. Restart your browser
4. Check Apache error logs at `C:\xampp\apache\logs\error.log`

### Apache Won't Start
1. Check if ports 80 and 443 are available
2. Look for errors in `C:\xampp\apache\logs\error.log`
3. Ensure all required modules are enabled in httpd.conf

### Page Not Found
1. Verify the repository was cloned to the correct location
2. Check if Apache is running
3. Confirm the URL matches your project structure

## Project Structure
```
IAS-FINAL/
├── IAS-LAB2-PART-3/
│   ├── app/
│   │   ├── views/
│   │   ├── controllers/
│   │   └── models/
│   ├── assets/
│   ├── .htaccess
│   └── openssl.cnf
```

## Security Features
- HTTPS encryption for secure communication
- SSL certificate for localhost development
- Apache security configurations
- Form validation and sanitization

## Development Notes
- Always use HTTPS during development
- Check Apache logs for debugging
- Keep SSL certificates up to date

## Common Issues and Solutions
1. **"Your connection is not private" warning**
   - Reinstall the SSL certificate
   - Clear browser cache

2. **Apache fails to start**
   - Check if another service is using ports 80/443
   - Verify SSL configuration

3. **500 Internal Server Error**
   - Check Apache error logs
   - Verify .htaccess configuration

## Additional Resources
- [XAMPP Documentation](https://www.apachefriends.org/docs/)
- [Apache SSL Documentation](https://httpd.apache.org/docs/2.4/ssl/)
- [OpenSSL Documentation](https://www.openssl.org/docs/)

## Support
For issues or questions:
- Check the troubleshooting section
- Review Apache error logs
- Contact the development team

## License
[Your License Information] 