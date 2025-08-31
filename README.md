# Gchat - Global Chat

A simple single-file global chat application made with PHP, jQuery, and MySQL. Real-time messaging in a clean, modern interface.

## Features

- 🚀 **Single File Solution** - Everything in one PHP file
- 💬 **Real-time Messaging** - Live chat with 1-second polling
- 🔐 **User Authentication** - Register and login system
- 🎨 **Modern UI** - Clean, responsive design with animations
- 📱 **Mobile Friendly** - Works on all devices
- ⚡ **Smart Auth** - Auto-detects if username exists for login/signup
- 🔄 **Message Retry** - Failed messages can be retried
- ⏱️ **Anti-Spam** - 2-second cooldown between messages
- 🎯 **Real-time Updates** - No page refresh needed

## Quick Setup

1. **Requirements**
   - PHP 7.0+
   - MySQL/MariaDB
   - Web server (Apache/Nginx)

2. **Installation**
   ```bash
   # Clone or download the gchat.php file
   # Place it in your web server directory
   # Update database credentials in the file if needed
   ```

3. **Database Configuration**
   ```php
   $host = 'localhost';
   $dbname = 'gchat';
   $username = 'root';
   $password = '';
   ```

4. **Run**
   - Access `gchat.php` in your browser
   - Database and tables are created automatically
   - Start chatting!

## How It Works

- **Authentication**: Users can register or login with username/password
- **Real-time**: JavaScript polls server every second for new messages
- **Security**: Passwords are hashed, SQL injection protected
- **Responsive**: Modern CSS with mobile-first design
- **Smart UX**: Message status indicators, retry functionality, cooldown system

## What's Inside

**gchat.php** - Single file containing:
- PHP backend (database setup, authentication, message handling)
- HTML interface (login/register forms, chat UI)
- CSS styling (modern responsive design)
- JavaScript (real-time messaging, AJAX communication)

## Browser Support

- Chrome/Edge/Safari/Firefox
- Mobile browsers
- IE11+ (with limitations)

## Customization

Easy to customize by modifying the CSS variables and color scheme in the `<style>` section.

## License

MIT License - Feel free to use and modify!
