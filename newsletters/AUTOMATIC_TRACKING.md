# Automatic Email Open Tracking

## Overview

The newsletter system now **automatically** adds tracking pixels to all sent emails. You no longer need to manually edit HTML or embed tracking code!

## How It Works

### 1. Configure Tracking URL

Edit your newsletter configuration and set the **Tracking Pixel URL** field:

```
Example: https://learn.bcpublicservice.gov.bc.ca/newsletter-tracker/track.php
```

### 2. Send Newsletter

When you send a newsletter, the system automatically:
- Generates a unique tracking ID for each recipient
- Appends a 1x1 transparent pixel to the email HTML
- Injects the pixel before `</body>` tag (or at the end if no body tag)

### 3. Track Opens

When recipients open the email and load images:
- The tracking pixel makes a request to your tracking server
- The `track.php` script logs the open event
- You can view open rates in the campaign dashboard

## Tracking Pixel Format

The system automatically generates tracking pixels like this:

```html
<img src="https://your-server.com/track.php?id=abc123...&e=user%40example.com&n=1&c=100"
     width="1"
     height="1"
     border="0"
     alt=""
     style="display:block;width:1px;height:1px;border:0;">
```

### Query Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `id` | Unique tracking identifier (MD5 hash) | `abc123def456...` |
| `e` | Recipient email (URL encoded) | `user%40example.com` |
| `n` | Newsletter ID | `1` |
| `c` | Campaign ID | `100` |

## Configuration

### Enable Tracking

1. Go to **Newsletter Settings**
2. Scroll to **Email Tracking Configuration**
3. Set **Tracking Pixel URL** to your track.php location
4. Save the configuration

### Disable Tracking

Leave the **Tracking Pixel URL** field empty or set it to blank.

## Important Limitations

⚠️ **Email clients often block images by default**

- **Corporate email clients** (Outlook, Gmail with high security) block images
- Users must click "Show Images" to trigger tracking
- **Actual open rates are typically 20-40% higher** than tracked rates
- Consider tracking as a "minimum open rate" indicator

### Best Practices

1. **Don't rely solely on pixel tracking** - Combine with:
   - Link click tracking (more reliable)
   - Web version links (100% trackable)
   - User engagement metrics

2. **Set realistic expectations**:
   - 30% tracked open rate ≈ 50-70% actual open rate
   - Mobile clients are more likely to show images
   - Desktop/corporate clients often block images

3. **Add fallbacks**:
   - Include "View web version" link
   - Add "If images don't display, click here" message
   - Track link clicks for more accurate engagement data

## Technical Implementation

### Function: `injectTrackingPixel()`

Located in: `inc/lsapp.php:2495-2536`

```php
function injectTrackingPixel(
    $htmlBody,           // HTML email content
    $trackingUrl,        // Base tracking URL
    $recipientEmail,     // Recipient's email
    $newsletterId,       // Newsletter ID
    $campaignId          // Campaign ID
) {
    // Returns HTML with tracking pixel injected
}
```

### Automatic Injection

Located in: `newsletters/send_newsletter.php:155-162`

```php
foreach ($activeSubscribers as $subscriber) {
    // Inject tracking pixel for this recipient
    $htmlBodyWithTracking = injectTrackingPixel(
        $htmlBody,
        $newsletter['tracking_url'] ?? null,
        $subscriber,
        $newsletterId,
        $campaignId
    );

    // Queue email with tracking...
}
```

## Security Features

✅ **URL Encoding**: All parameters are properly URL-encoded using `http_build_query()`

✅ **XSS Protection**: Output is escaped with `htmlspecialchars(ENT_QUOTES, 'UTF-8')`

✅ **Unique IDs**: Tracking IDs use MD5 + random bytes to prevent enumeration

✅ **No sensitive data**: Tracking URLs contain only necessary parameters

## Database Schema

### Migration Applied

```sql
ALTER TABLE newsletters ADD COLUMN tracking_url TEXT;
```

Run the migration:
```bash
php newsletters/add_tracking_url.php
```

## Monitoring Tracking

View campaign open rates in:
- **Campaign Monitor** (`campaign_monitor.php`)
- Individual campaign details
- Aggregate statistics across campaigns

## Troubleshooting

### Tracking pixels not appearing in sent emails

1. Check that `tracking_url` is set in newsletter configuration
2. Verify migration was applied (`tracking_url` column exists)
3. Check email queue - tracking pixels are added when emails are queued

### Track.php not logging opens

1. Ensure track.php is deployed to the tracking server
2. Check file permissions on tracking database
3. Verify URL is publicly accessible (not behind authentication)
4. Check CORS/firewall settings if on different domain

### High bounce/block rate

- Ensure tracking domain has proper DNS records
- Add SPF/DKIM/DMARC records
- Use HTTPS for tracking URLs
- Consider using same domain as email sender

## Migration Checklist

- [x] Add `tracking_url` column to newsletters table
- [x] Update newsletter edit form to include tracking URL field
- [x] Create `injectTrackingPixel()` helper function
- [x] Update `send_newsletter.php` to auto-inject pixels
- [x] Document feature in tracking_example.html
- [x] Create migration script
- [x] Add tests for pixel injection

## See Also

- `public_tracking/tracking_example.html` - Examples and limitations
- `public_tracking/track.php` - Tracking pixel handler (deploy separately)
- `network-diagram.md` - System architecture
- `security-review.md` - Security considerations
