<?php
/**
 * Moje Blsk AI - Contact Form Email Handler
 * Uses Hostinger SMTP to send emails
 */

// Allow CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// SMTP Configuration - Hostinger
$smtp_host = 'smtp.hostinger.com';
$smtp_port = 465;
$smtp_user = 'info@mojebleskai.cz';
$smtp_pass = '@@Blesk1122';
$from_email = 'info@mojebleskai.cz';
$from_name = 'Moje Blsk AI';
$to_email = 'info@mojebleskai.cz';

// Get form data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Try regular POST data
    $data = $_POST;
}

// Validate required fields
$required_fields = ['first_name', 'last_name', 'email', 'phone'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// Sanitize input
$first_name = htmlspecialchars(strip_tags($data['first_name']));
$last_name = htmlspecialchars(strip_tags($data['last_name']));
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$phone = htmlspecialchars(strip_tags($data['phone']));
$company = htmlspecialchars(strip_tags($data['company'] ?? ''));
$website = htmlspecialchars(strip_tags($data['website'] ?? ''));
$message = htmlspecialchars(strip_tags($data['message'] ?? ''));

// Form selections
$website_type = htmlspecialchars(strip_tags($data['website_type'] ?? 'Not specified'));
$budget = htmlspecialchars(strip_tags($data['budget'] ?? 'Not specified'));
$timeline = htmlspecialchars(strip_tags($data['timeline'] ?? 'Not specified'));
$features = $data['features'] ?? [];
if (is_array($features)) {
    $features = implode(', ', array_map('htmlspecialchars', $features));
} else {
    $features = htmlspecialchars(strip_tags($features));
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Build email content
$subject = "New Quote Request from $first_name $last_name";

$email_body = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a1a2e; color: #b9ff66; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
        .section { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd; }
        .section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .label { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; }
        .value { font-size: 16px; margin-top: 5px; }
        .highlight { background: #b9ff66; color: #1a1a2e; padding: 2px 8px; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1 style='margin:0;'>🚀 New Quote Request</h1>
            <p style='margin:10px 0 0 0;opacity:0.8;'>Moje Blsk AI Website</p>
        </div>
        <div class='content'>
            <div class='section'>
                <h2 style='margin-top:0;color:#1a1a2e;'>📋 Project Details</h2>
                <p><span class='label'>Website Type:</span><br><span class='value highlight'>$website_type</span></p>
                <p><span class='label'>Budget Range:</span><br><span class='value highlight'>$budget</span></p>
                <p><span class='label'>Timeline:</span><br><span class='value highlight'>$timeline</span></p>
                <p><span class='label'>Required Features:</span><br><span class='value'>$features</span></p>
            </div>
            
            <div class='section'>
                <h2 style='margin-top:0;color:#1a1a2e;'>👤 Contact Information</h2>
                <p><span class='label'>Name:</span><br><span class='value'>$first_name $last_name</span></p>
                <p><span class='label'>Email:</span><br><span class='value'><a href='mailto:$email'>$email</a></span></p>
                <p><span class='label'>Phone:</span><br><span class='value'><a href='tel:$phone'>$phone</a></span></p>
                " . ($company ? "<p><span class='label'>Company:</span><br><span class='value'>$company</span></p>" : "") . "
                " . ($website ? "<p><span class='label'>Current Website:</span><br><span class='value'><a href='$website'>$website</a></span></p>" : "") . "
            </div>
            
            " . ($message ? "
            <div class='section'>
                <h2 style='margin-top:0;color:#1a1a2e;'>💬 Additional Message</h2>
                <p style='background:#fff;padding:15px;border-radius:8px;border-left:4px solid #b9ff66;'>$message</p>
            </div>
            " : "") . "
            
            <div style='text-align:center;padding-top:20px;color:#666;font-size:12px;'>
                <p>This email was sent from the Moje Blsk AI website contact form.</p>
            </div>
        </div>
    </div>
</body>
</html>
";

// Send email using PHPMailer-style SMTP or native mail
// First, try using PHP's built-in mail with proper headers
// For production with Hostinger, PHPMailer is recommended

// Check if PHPMailer is available
$phpmailer_path = __DIR__ . '/PHPMailer/PHPMailerAutoload.php';
$phpmailer_src = __DIR__ . '/vendor/autoload.php';

if (file_exists($phpmailer_src)) {
    // Use Composer autoload
    require $phpmailer_src;
    $use_phpmailer = true;
} elseif (file_exists($phpmailer_path)) {
    // Use PHPMailer directly
    require $phpmailer_path;
    $use_phpmailer = true;
} else {
    $use_phpmailer = false;
}

$success = false;
$error_message = '';

if ($use_phpmailer && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    // Use PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $smtp_port;
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email);
        $mail->addReplyTo($email, "$first_name $last_name");
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $email_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $email_body));
        
        $mail->send();
        $success = true;
    } catch (Exception $e) {
        $error_message = $mail->ErrorInfo;
    }
} else {
    // Fallback: Use native PHP mail() with headers
    // Note: This may not work on all servers, especially without proper SMTP config
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $first_name . ' ' . $last_name . ' <' . $email . '>',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $success = mail($to_email, $subject, $email_body, implode("\r\n", $headers));
    
    if (!$success) {
        $error_message = 'Failed to send email. Please try again or contact us directly.';
    }
}

// Send response
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your request has been sent successfully. We\'ll get back to you within 24 hours.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $error_message ?: 'Failed to send email. Please try again.'
    ]);
}
?>
