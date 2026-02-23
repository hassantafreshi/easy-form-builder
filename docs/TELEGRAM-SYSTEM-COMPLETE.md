# Telegram Notification System - Complete Implementation Guide

## Overview
Complete Telegram notification addon for Easy Form Builder plugin, following the established SMS system architecture.

## System Architecture

### 1. File Structure
```
vendor/telegram/
├── telegram-new-efb.php         # Core functionality (like smsefb.php)
├── class-Emsfb-telegram.php     # UI management (like class-Emsfb-sms.php)
├── onboarding.html              # User onboarding interface
└── assets/
    └── js/
        └── telegram-efb.js       # Frontend JavaScript
```

### 2. Database Schema
```sql
-- Main Telegram settings table
CREATE TABLE wp_emsfb_telegram_stts_efb (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    bot_token VARCHAR(255),
    bot_username VARCHAR(100),
    admin_chat_ids TEXT,
    received_message_noti_user TEXT,
    new_message_noti_user TEXT,
    new_response_noti TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_form (form_id)
);

-- Telegram message history table
CREATE TABLE wp_emsfb_telegram_history_efb (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    chat_id BIGINT NOT NULL,
    message_text TEXT,
    message_type ENUM('notification', 'response', 'admin', 'system'),
    tracking_code VARCHAR(100),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    telegram_message_id BIGINT,
    INDEX idx_form_id (form_id),
    INDEX idx_chat_id (chat_id),
    INDEX idx_tracking_code (tracking_code)
);
```

## 3. Core Components

### A. telegramsendefb Class (telegram-new-efb.php)
**Primary Functions:**
- `send_telegram_efb()` - Main message sending function
- `verify_bot_token()` - Validate Telegram bot tokens
- `handle_webhook_efb()` - Process incoming webhook data
- `get_telegram_contact_efb()` - Retrieve form Telegram settings
- `generate_onboarding_url()` - Create setup URL for new users
- `check_for_new_updates()` - Monitor for new chat registrations

**Key Features:**
- Multi-chat broadcasting (admin notifications)
- Message templating with placeholders
- Error handling and retry logic
- Chat ID validation and management
- Bot token verification with Telegram API

### B. Emsfb_telegram Class (class-Emsfb-telegram.php)
**Primary Functions:**
- `render_telegram_settings()` - Admin settings interface
- `save_telegram_config()` - Save configuration data
- `display_telegram_stats()` - Show usage statistics
- `generate_onboarding_link()` - Create user setup links

**UI Components:**
- Bot token input and verification
- Chat ID management interface
- Message template editors
- Test message functionality
- Onboarding link generation

### C. Frontend JavaScript (telegram-efb.js)
**Key Functions:**
- `verifyBotToken()` - AJAX bot verification
- `sendTestTelegram()` - Send test messages
- `generateOnboardingLink()` - Create setup URLs
- `updateTelegramSettings()` - Save configuration

## 4. Integration Points

### A. WordPress Hooks
```php
// Constructor registrations in class-Emsfb-admin.php
add_action('wp_ajax_send_telegram_test_efb', [$this, 'send_telegram_admin_Emsfb']);
add_action('wp_ajax_verify_telegram_bot_efb', [$this, 'verify_telegram_bot_Emsfb']);
add_action('wp_ajax_telegram_activate_efb', [$this, 'telegram_activate_efb']);
add_action('wp_ajax_telegram_check_status_efb', [$this, 'telegram_check_status_efb']);
```

### B. Form Processing Integration (functions.php)
```php
// New form submission notification
if ($state == "fform") {
    $this->telegram_ready_for_send_efb($form_id, $page_url, 'fform', $tracking_code);
}

// Admin response notification
if ($state == "respp" || $state == "respadmin") {
    $this->telegram_ready_for_send_efb($form_id, $page_url, $state, $tracking_code);
}
```

### C. Form Builder UI Integration (val-efb.js)
- Telegram notification checkbox in form settings
- Template message editors
- Bot configuration interface
- Real-time status indicators

## 5. Security Features

### A. Authentication & Authorization
- WordPress nonce verification for all AJAX calls
- Bot token validation with Telegram API
- Chat ID verification before message sending
- Admin capability checks (`Emsfb_telegram_efb`)

### B. Data Sanitization
- Input sanitization for all user data
- SQL injection prevention with prepared statements
- XSS protection for message content
- Bot token encryption in database

### C. Rate Limiting
- API call throttling to prevent abuse
- Message queue for bulk notifications
- Error retry with exponential backoff
- Daily sending limits per form

## 6. User Onboarding Process

### A. Admin Setup
1. Navigate to Forms → Telegram Settings
2. Enter Bot Token and verify
3. Configure message templates
4. Generate onboarding link for users

### B. User Registration
1. Click onboarding link (opens onboarding.html)
2. Start Telegram bot via provided link
3. Send `/start [form_id]_[nonce]` command
4. System automatically registers Chat ID
5. Confirmation message sent to user

### C. Automatic Flow
```
Admin generates link → User clicks → Bot starts → Chat ID captured → Notifications active
```

## 7. Message Templates & Variables

### Available Placeholders:
- `[confirmation_code]` - Tracking/confirmation code
- `[link_page]` - Original page URL
- `[link_domain]` - Website domain
- `[link_response]` - Response tracking link
- `[website_name]` - Site title from WordPress

### Template Types:
- **received_message_noti_user** - User acknowledgment
- **new_message_noti_user** - New submission alert
- **new_response_noti** - Response notification

## 8. API Endpoints

### A. AJAX Handlers
- `telegram_activate_efb` - Complete user onboarding
- `telegram_check_status_efb` - Verify activation status
- `send_telegram_test_efb` - Send test messages
- `verify_telegram_bot_efb` - Validate bot tokens

### B. Webhook Support
- Incoming message processing
- Chat ID registration
- Command handling (/start, /help)
- Status updates

## 9. Error Handling & Logging

### Error Types:
- **API Errors**: Invalid bot tokens, network issues
- **Configuration Errors**: Missing settings, invalid chat IDs
- **Message Errors**: Failed sends, rate limits
- **System Errors**: Database issues, file permissions

### Logging System:
```php
// Example error logging
error_log("[EFB Telegram] Send failed - Form: $form_id, Chat: $chat_id, Error: " . $error_message);
```

## 10. Performance Optimization

### A. Caching Strategy
- Bot information caching (30 minutes)
- Chat ID validation caching
- Message template caching
- API response caching

### B. Batch Processing
- Multiple chat ID sending in single API call
- Message queuing for high-volume forms
- Background processing for large notifications
- Retry queue for failed messages

## 11. Testing & Validation

### A. Unit Tests
- Bot token validation
- Message formatting
- Chat ID management
- Error handling

### B. Integration Tests
- End-to-end onboarding flow
- Form submission → notification delivery
- Admin response → user notification
- Webhook processing

### C. Load Testing
- High-volume message sending
- Multiple concurrent form submissions
- API rate limit handling
- Database performance under load

## 12. Maintenance & Monitoring

### A. Health Checks
- Daily bot token validation
- Chat ID accessibility verification
- API endpoint monitoring
- Database integrity checks

### B. Analytics & Reporting
- Message delivery statistics
- User engagement metrics
- Error rate monitoring
- Performance benchmarking

## 13. Deployment Checklist

### Pre-Deployment:
- [ ] Database tables created
- [ ] Bot token configured and verified
- [ ] Message templates customized
- [ ] AJAX endpoints tested
- [ ] Onboarding flow validated
- [ ] Error logging enabled

### Post-Deployment:
- [ ] Send test notifications
- [ ] Verify user onboarding process
- [ ] Check admin dashboard functionality
- [ ] Monitor error logs
- [ ] Validate webhook processing
- [ ] Test form integration

## 14. Support & Troubleshooting

### Common Issues:
1. **Bot Token Invalid**: Re-verify with @BotFather
2. **Chat ID Not Found**: Complete onboarding process
3. **Messages Not Sending**: Check network/API limits
4. **Webhook Failures**: Verify SSL and endpoint accessibility

### Debug Mode:
```php
// Enable debug logging
define('EFB_TELEGRAM_DEBUG', true);
```

---

## Implementation Status: ✅ COMPLETE

**All components implemented and integrated:**
- ✅ Core functionality (telegram-new-efb.php)
- ✅ UI management (class-Emsfb-telegram.php)
- ✅ Frontend interface (telegram-efb.js)
- ✅ Database schema and operations
- ✅ WordPress integration (AJAX handlers)
- ✅ Form processing integration (functions.php)
- ✅ User onboarding system (onboarding.html)
- ✅ Security and validation
- ✅ Error handling and logging

**Ready for production deployment and testing.**