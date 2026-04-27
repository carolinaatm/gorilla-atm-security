<?php
/**
 * Gorilla ATM Security — Web-to-Lead Proxy
 * Validates Google reCAPTCHA v2, strips the token, forwards clean data to Salesforce.
 */

$secretKey    = '6Le7o5YsAAAAAAkmh0L7jQ1OXBBkEE0LlH0mJInW';
$thankYouUrl  = 'https://gorillaatmsecurity.com/thank-you/';
$errorUrl     = 'https://gorillaatmsecurity.com/contact.html?error=captcha';
$salesforceUrl = 'https://webto.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8';

// 1. Grab reCAPTCHA token
$captchaToken = $_POST['g-recaptcha-response'] ?? '';

if (empty($captchaToken)) {
    header('Location: ' . $errorUrl);
    exit;
}

// 2. Verify with Google
$verifyResponse = file_get_contents(
    'https://www.google.com/recaptcha/api/siteverify?secret='
    . urlencode($secretKey)
    . '&response='
    . urlencode($captchaToken)
);

$result = json_decode($verifyResponse, true);

if (empty($result['success'])) {
    header('Location: ' . $errorUrl);
    exit;
}

// 3. Strip reCAPTCHA field and forward to Salesforce
$fields = $_POST;
unset($fields['g-recaptcha-response']);

$postData = http_build_query($fields);

$context = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n"
                         . "Content-Length: " . strlen($postData) . "\r\n",
        'content'       => $postData,
        'ignore_errors' => true,
        'timeout'       => 10,
    ],
    'ssl' => [
        'verify_peer' => true,
    ]
]);

file_get_contents($salesforceUrl, false, $context);

// 4. Redirect to thank-you page
header('Location: ' . $thankYouUrl);
exit;
