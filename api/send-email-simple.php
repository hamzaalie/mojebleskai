<?php
/**
 * Moje Blsk AI - Simple SMTP Email Handler
 * Direct SMTP connection to Hostinger (no external dependencies)
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Configuration
$config = [
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_user' => 'info@mojebleskai.cz',
    'smtp_pass' => '@@Blesk1122',
    'from_email' => 'info@mojebleskai.cz',
    'from_name' => 'Moje Blsk AI',
    'to_email' => 'info@mojebleskai.cz'
];

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$required = ['first_name', 'last_name', 'email', 'phone'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => "Missing: $field"]));
    }
}

// Sanitize
$data = [
    'first_name' => htmlspecialchars(strip_tags($input['first_name'])),
    'last_name' => htmlspecialchars(strip_tags($input['last_name'])),
    'email' => filter_var($input['email'], FILTER_SANITIZE_EMAIL),
    'phone' => htmlspecialchars(strip_tags($input['phone'])),
    'company' => htmlspecialchars(strip_tags($input['company'] ?? '')),
    'website' => htmlspecialchars(strip_tags($input['website'] ?? '')),
    'message' => htmlspecialchars(strip_tags($input['message'] ?? '')),
    'website_type' => htmlspecialchars(strip_tags($input['website_type'] ?? 'Not specified')),
    'budget' => htmlspecialchars(strip_tags($input['budget'] ?? 'Not specified')),
    'timeline' => htmlspecialchars(strip_tags($input['timeline'] ?? 'Not specified')),
    'features' => is_array($input['features'] ?? null) ? implode(', ', $input['features']) : ($input['features'] ?? 'None selected')
];

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid email']));
}

// Build email
$subject = "New Quote Request from {$data['first_name']} {$data['last_name']}";
$boundary = md5(time());

$body = "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$body .= "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;'>";
$body .= "<div style='max-width:600px;margin:0 auto;'>";
$body .= "<div style='background:#1a1a2e;color:#b9ff66;padding:20px;text-align:center;border-radius:8px 8px 0 0;'>";
$body .= "<h1 style='margin:0;'>🚀 New Quote Request</h1></div>";
$body .= "<div style='background:#f5f5f5;padding:20px;border-radius:0 0 8px 8px;'>";
$body .= "<h2>📋 Project Details</h2>";
$body .= "<p><strong>Website Type:</strong> {$data['website_type']}</p>";
$body .= "<p><strong>Budget:</strong> {$data['budget']}</p>";
$body .= "<p><strong>Timeline:</strong> {$data['timeline']}</p>";
$body .= "<p><strong>Features:</strong> {$data['features']}</p>";
$body .= "<hr style='border:1px solid #ddd;margin:20px 0;'>";
$body .= "<h2>👤 Contact Information</h2>";
$body .= "<p><strong>Name:</strong> {$data['first_name']} {$data['last_name']}</p>";
$body .= "<p><strong>Email:</strong> <a href='mailto:{$data['email']}'>{$data['email']}</a></p>";
$body .= "<p><strong>Phone:</strong> {$data['phone']}</p>";
if ($data['company']) $body .= "<p><strong>Company:</strong> {$data['company']}</p>";
if ($data['website']) $body .= "<p><strong>Current Website:</strong> {$data['website']}</p>";
if ($data['message']) {
    $body .= "<hr style='border:1px solid #ddd;margin:20px 0;'>";
    $body .= "<h2>💬 Message</h2><p>{$data['message']}</p>";
}
$body .= "</div></div></body></html>\r\n";
$body .= "--$boundary--\r\n";

// SMTP Function
function smtp_send($config, $to, $subject, $body, $replyTo) {
    $socket = @fsockopen('ssl://' . $config['smtp_host'], $config['smtp_port'], $errno, $errstr, 30);
    if (!$socket) {
        return "Connection failed: $errstr ($errno)";
    }
    
    stream_set_timeout($socket, 30);
    
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') return "Server error: $response";
    
    // EHLO
    fputs($socket, "EHLO " . $config['smtp_host'] . "\r\n");
    while ($line = fgets($socket, 515)) {
        if (substr($line, 3, 1) == ' ') break;
    }
    
    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);
    
    fputs($socket, base64_encode($config['smtp_user']) . "\r\n");
    fgets($socket, 515);
    
    fputs($socket, base64_encode($config['smtp_pass']) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') return "Auth failed: $response";
    
    // MAIL FROM
    fputs($socket, "MAIL FROM:<{$config['from_email']}>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return "MAIL FROM error: $response";
    
    // RCPT TO
    fputs($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return "RCPT TO error: $response";
    
    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '354') return "DATA error: $response";
    
    // Headers
    $boundary = md5(time());
    $headers = "From: {$config['from_name']} <{$config['from_email']}>\r\n";
    $headers .= "Reply-To: $replyTo\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "\r\n";
    
    fputs($socket, $headers . $body . "\r\n.\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return "Send error: $response";
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

// Send email
$result = smtp_send(
    $config,
    $config['to_email'],
    $subject,
    $body,
    "{$data['first_name']} {$data['last_name']} <{$data['email']}>"
);

if ($result === true) {
    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $result]);
}
?>
