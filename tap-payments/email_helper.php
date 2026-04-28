<?php
/**
 * EN: Handles application behavior in `tap-payments/email_helper.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/email_helper.php`.
 */
/**
 * Tap Payments - Email Helper
 * 
 * Sends payment confirmation emails with vouchers/receipts
 */
require_once __DIR__ . '/config.php';

/**
 * Send payment confirmation email with voucher/receipt
 */
function sendPaymentConfirmationEmail($customerEmail, $customerName, $paymentData) {
    $subject = 'Payment Confirmation - ' . ($paymentData['plan'] ?? 'Subscription');
    
    // Build HTML email with voucher/receipt
    $html = buildPaymentVoucherEmail($customerName, $paymentData);
    
    return sendTapEmail($customerEmail, $subject, $html);
}

/**
 * Send payment failure notification email
 */
function sendPaymentFailureEmail($customerEmail, $customerName, $paymentData) {
    $subject = 'Payment Not Completed - ' . ($paymentData['plan'] ?? 'Subscription');
    
    $html = buildPaymentFailureEmail($customerName, $paymentData);
    
    return sendTapEmail($customerEmail, $subject, $html);
}

/**
 * Build HTML email template for payment voucher/receipt
 */
function buildPaymentVoucherEmail($customerName, $paymentData) {
    $plan = $paymentData['plan'] ?? 'Subscription';
    $amount = number_format($paymentData['amount'] ?? 0, 2);
    $tax = number_format($paymentData['tax'] ?? 0, 2);
    $total = number_format($paymentData['total'] ?? 0, 2);
    $tapId = $paymentData['tap_id'] ?? '';
    $registrationId = $paymentData['registration_id'] ?? '';
    $date = date('F d, Y');
    $time = date('h:i A');
    $description = $paymentData['description'] ?? 'SaaS Subscription';
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">✓ Payment Confirmed</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #333; font-size: 16px; margin: 0 0 20px 0;">Dear ' . htmlspecialchars($customerName) . ',</p>
                            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;">
                                Thank you for your payment! Your transaction has been successfully processed and confirmed.
                            </p>
                            
                            <!-- Voucher/Receipt Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border: 2px solid #28a745; border-radius: 8px; padding: 20px; margin: 25px 0;">
                                <tr>
                                    <td>
                                        <h2 style="color: #28a745; margin: 0 0 20px 0; font-size: 20px; text-align: center;">PAYMENT RECEIPT</h2>
                                        
                                        <table width="100%" cellpadding="8" cellspacing="0" style="margin-bottom: 15px;">
                                            <tr>
                                                <td style="color: #666; font-size: 14px; padding: 5px 0;">Date:</td>
                                                <td style="color: #333; font-size: 14px; font-weight: 600; text-align: right; padding: 5px 0;">' . htmlspecialchars($date) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666; font-size: 14px; padding: 5px 0;">Time:</td>
                                                <td style="color: #333; font-size: 14px; font-weight: 600; text-align: right; padding: 5px 0;">' . htmlspecialchars($time) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666; font-size: 14px; padding: 5px 0;">Description:</td>
                                                <td style="color: #333; font-size: 14px; font-weight: 600; text-align: right; padding: 5px 0;">' . htmlspecialchars($description) . '</td>
                                            </tr>
                                            ' . (!empty($registrationId) ? '<tr>
                                                <td style="color: #666; font-size: 14px; padding: 5px 0;">Registration ID:</td>
                                                <td style="color: #333; font-size: 14px; font-weight: 600; text-align: right; padding: 5px 0;">#' . htmlspecialchars($registrationId) . '</td>
                                            </tr>' : '') . '
                                            ' . (!empty($tapId) ? '<tr>
                                                <td style="color: #666; font-size: 14px; padding: 5px 0;">Transaction ID:</td>
                                                <td style="color: #333; font-size: 14px; font-weight: 600; text-align: right; padding: 5px 0;">' . htmlspecialchars($tapId) . '</td>
                                            </tr>' : '') . '
                                        </table>
                                        
                                        <hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">
                                        
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="color: #666; font-size: 15px; padding: 8px 0;">Subtotal:</td>
                                                <td style="color: #333; font-size: 15px; font-weight: 600; text-align: right; padding: 8px 0;">$' . htmlspecialchars($amount) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666; font-size: 15px; padding: 8px 0;">Tax (15%):</td>
                                                <td style="color: #333; font-size: 15px; font-weight: 600; text-align: right; padding: 8px 0;">$' . htmlspecialchars($tax) . '</td>
                                            </tr>
                                            <tr style="background-color: #e8f5e9;">
                                                <td style="color: #28a745; font-size: 18px; font-weight: 700; padding: 12px 0; border-top: 2px solid #28a745;">Total Paid:</td>
                                                <td style="color: #28a745; font-size: 18px; font-weight: 700; text-align: right; padding: 12px 0; border-top: 2px solid #28a745;">$' . htmlspecialchars($total) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 25px 0 0 0;">
                                This email serves as your official receipt. Please keep it for your records.
                            </p>
                            
                            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 20px 0 0 0;">
                                If you have any questions about this payment, please contact our support team.
                            </p>
                            
                            <p style="color: #555; font-size: 15px; margin: 30px 0 0 0;">
                                Best regards,<br>
                                <strong style="color: #333;">Ratib Program Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;">
                            <p style="color: #888; font-size: 12px; margin: 0;">
                                This is an automated email. Please do not reply directly to this message.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    
    return $html;
}

/**
 * Build HTML email template for payment failure
 */
function buildPaymentFailureEmail($customerName, $paymentData) {
    $reason = $paymentData['reason'] ?? 'Payment could not be completed';
    $tapId = $paymentData['tap_id'] ?? '';
    $amount = isset($paymentData['amount']) ? number_format($paymentData['amount'], 2) : '';
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Not Completed</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #dc3545; padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">⚠ Payment Not Completed</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #333; font-size: 16px; margin: 0 0 20px 0;">Dear ' . htmlspecialchars($customerName) . ',</p>
                            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                                We wanted to inform you that your payment attempt was not completed successfully.
                            </p>
                            
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                                <p style="color: #856404; font-size: 14px; margin: 0; font-weight: 600;">Reason:</p>
                                <p style="color: #856404; font-size: 14px; margin: 5px 0 0 0;">' . htmlspecialchars($reason) . '</p>
                            </div>
                            
                            ' . (!empty($tapId) ? '<p style="color: #666; font-size: 13px; margin: 15px 0 0 0;">
                                <strong>Reference ID:</strong> ' . htmlspecialchars($tapId) . '
                            </p>' : '') . '
                            
                            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 25px 0 0 0;">
                                Please try again with a different payment method or contact our support team if you continue to experience issues.
                            </p>
                            
                            <p style="color: #555; font-size: 15px; margin: 30px 0 0 0;">
                                Best regards,<br>
                                <strong style="color: #333;">Ratib Program Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;">
                            <p style="color: #888; font-size: 12px; margin: 0;">
                                This is an automated email. Please do not reply directly to this message.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    
    return $html;
}

/**
 * Send email using existing email infrastructure
 */
function sendTapEmail($to, $subject, $htmlBody) {
    // Try to use existing email function from contacts API
    $contactsApiPath = __DIR__ . '/../api/contacts/simple_contacts.php';
    if (file_exists($contactsApiPath)) {
        require_once $contactsApiPath;
        if (function_exists('sendContactEmail')) {
            return sendContactEmail($to, $subject, $htmlBody);
        }
    }
    
    // Fallback: Use basic PHP mail() or PHPMailer if available
    $fromEmail = defined('SMTP_FROM_EMAIL') ? constant('SMTP_FROM_EMAIL') : 'noreply@ratibprogram.com';
    $fromName = defined('SMTP_FROM_NAME') ? constant('SMTP_FROM_NAME') : 'Ratib Program';
    
    // Try PHPMailer if available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP settings if available
            if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
                $mail->isSMTP();
                $mail->Host = constant('SMTP_HOST');
                $mail->SMTPAuth = true;
                $mail->Username = constant('SMTP_USER');
                $mail->Password = constant('SMTP_PASS');
                $mail->SMTPSecure = defined('SMTP_SECURE') ? constant('SMTP_SECURE') : 'tls';
                $mail->Port = defined('SMTP_PORT') ? constant('SMTP_PORT') : 587;
            }
            
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Tap payment email failed: " . $mail->ErrorInfo);
            // Fall through to mail() fallback
        }
    }
    
    // Fallback to PHP mail()
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    $result = @mail($to, $subject, $htmlBody, $headers);
    if (!$result) {
        error_log("Tap payment email failed via mail() to {$to}");
    }
    
    return $result;
}
