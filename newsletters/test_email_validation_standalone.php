<?php
/**
 * Standalone Email Validation Test Suite
 * Tests the validateEmail() function against various edge cases
 */

// Copy of validateEmail function for standalone testing
function validateEmail($email) {
    // Basic type check
    if (!is_string($email)) {
        return false;
    }

    // Check for null bytes BEFORE trimming (security risk)
    if (strpos($email, "\0") !== false) {
        return false;
    }

    // Check for control characters and newlines BEFORE trimming (prevents email header injection)
    // Blocks ASCII control chars: 0x00-0x1F and DEL 0x7F
    if (preg_match('/[\x00-\x1F\x7F]/', $email)) {
        return false;
    }

    // Now safe to trim
    $email = trim($email);

    // Check if empty after trimming
    if ($email === '') {
        return false;
    }

    // Length check (RFC 5321: local part max 64, domain max 255, total max 254)
    if (strlen($email) > 254) {
        return false;
    }

    // Basic format validation using PHP's built-in filter
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Must have exactly one @ symbol
    if (substr_count($email, '@') !== 1) {
        return false;
    }

    // Split into local and domain parts
    list($local, $domain) = explode('@', $email);

    // Validate local part length (before @)
    if (strlen($local) > 64 || strlen($local) < 1) {
        return false;
    }

    // Validate domain part length (after @)
    if (strlen($domain) > 255 || strlen($domain) < 1) {
        return false;
    }

    // Check for consecutive dots (not allowed in email addresses)
    if (strpos($local, '..') !== false || strpos($domain, '..') !== false) {
        return false;
    }

    // Check for leading/trailing dots in local part
    if ($local[0] === '.' || $local[strlen($local) - 1] === '.') {
        return false;
    }

    // Domain must contain at least one dot
    if (strpos($domain, '.') === false) {
        return false;
    }

    return true;
}

// Test cases: [email, expected_result, description]
$testCases = [
    // Valid emails
    ['user@example.com', true, 'Standard valid email'],
    ['test.user@example.com', true, 'Email with dot in local part'],
    ['user+tag@example.co.uk', true, 'Email with plus sign and TLD'],
    ['first.last@subdomain.example.com', true, 'Email with subdomain'],

    // Invalid - Header injection attempts
    ["user@example.com\nBcc: attacker@evil.com", false, 'Newline injection attempt'],
    ["user@example.com\rBcc: attacker@evil.com", false, 'Carriage return injection'],
    ["user@example.com\x00", false, 'Null byte injection'],
    ["user@example.com\x0A", false, 'Line feed character'],

    // Invalid - Length violations
    [str_repeat('a', 65) . '@example.com', false, 'Local part too long (>64)'],
    ['user@' . str_repeat('a', 256) . '.com', false, 'Domain too long (>255)'],
    [str_repeat('a', 255) . '@example.com', false, 'Total length too long (>254)'],

    // Invalid - Format violations
    ['@example.com', false, 'Missing local part'],
    ['user@', false, 'Missing domain'],
    ['userexample.com', false, 'Missing @ symbol'],
    ['user@@example.com', false, 'Double @ symbol'],
    ['user..name@example.com', false, 'Consecutive dots in local'],
    ['user@example..com', false, 'Consecutive dots in domain'],
    ['.user@example.com', false, 'Leading dot in local part'],
    ['user.@example.com', false, 'Trailing dot in local part'],
    ['user@examplecom', false, 'Domain without dot'],

    // Invalid - Control characters
    ["user\x01@example.com", false, 'Control character in email'],
    ["user\x7F@example.com", false, 'DEL character in email'],

    // Edge cases - Empty/whitespace
    ['', false, 'Empty string'],
    ['   ', false, 'Only whitespace'],
    [' user@example.com ', true, 'Email with surrounding whitespace (trimmed)'],
];

echo "Email Validation Test Suite\n";
echo str_repeat('=', 80) . "\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $index => $test) {
    list($email, $expected, $description) = $test;

    $result = validateEmail($email);
    $status = ($result === $expected) ? '✓ PASS' : '✗ FAIL';

    if ($result === $expected) {
        $passed++;
        echo sprintf("✓ Test #%d: %s\n", $index + 1, $description);
    } else {
        $failed++;
        echo sprintf(
            "✗ FAIL | Test #%d: %s\n",
            $index + 1,
            $description
        );
        echo sprintf(
            "         Email: %s\n",
            json_encode($email)
        );
        echo sprintf(
            "         Expected: %s | Got: %s\n\n",
            $expected ? 'VALID' : 'INVALID',
            $result ? 'VALID' : 'INVALID'
        );
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo sprintf("Results: %d passed, %d failed out of %d tests\n", $passed, $failed, count($testCases));

if ($failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
