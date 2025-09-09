#!/bin/bash

# Email Queue Processor Cron Setup Script
# This script helps set up the cron job for processing the email queue

echo "Email Queue Processor - Cron Setup"
echo "==================================="
echo ""

# Get the current directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PHP_PATH=$(which php)

if [ -z "$PHP_PATH" ]; then
    echo "Error: PHP not found in PATH"
    exit 1
fi

echo "PHP path: $PHP_PATH"
echo "Script directory: $SCRIPT_DIR"
echo ""

# Create the cron job command
CRON_CMD="* * * * * cd $SCRIPT_DIR && $PHP_PATH process_email_queue.php >> email_queue.log 2>&1"

echo "The following cron job will run every minute to process queued emails:"
echo "$CRON_CMD"
echo ""
echo "To add this to your crontab, run:"
echo "  crontab -e"
echo ""
echo "Then add the following line:"
echo "  $CRON_CMD"
echo ""
echo "Or run this command to add it automatically:"
echo "  (crontab -l 2>/dev/null; echo \"$CRON_CMD\") | crontab -"
echo ""
echo "To view your current crontab:"
echo "  crontab -l"
echo ""
echo "To remove the cron job later:"
echo "  crontab -e"
echo "  (then delete the line)"
echo ""
echo "The processor will:"
echo "  - Run every minute"
echo "  - Process up to 30 emails per run (rate limit)"
echo "  - Log output to email_queue.log"
echo "  - Retry failed emails up to 3 times"
echo "  - Update campaign statuses automatically"