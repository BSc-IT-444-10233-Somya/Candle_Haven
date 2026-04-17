<?php
// includes/password-reset.php

require_once 'config.php';
require_once 'functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ================= OTP GENERATE ================= */
function generate_otp() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/* ================= SEND EMAIL ================= */
function send_otp_email($email, $otp, $userName = '') {
    try {
        $path = __DIR__ . '/../PHPMailer/src/';
        require_once $path . 'PHPMailer.php';
        require_once $path . 'SMTP.php';
        require_once $path . 'Exception.php';

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // 🔴 UPDATE HERE
        $mail->Username = 'somyakri2300@gmail.com';
        $mail->Password = 'lcgi uidj thhs mkxh';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('noreply@candlehaven.com', 'Candle Haven');
        $mail->addAddress($email, $userName);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP';

        $mail->Body = "
        <table width='100%' cellpadding='0' cellspacing='0' bgcolor='#f5e6d3' style='font-family: Arial, Verdana, sans-serif;'>
            <tr>
                <td align='center' style='padding: 20px;'>
                    <table width='600' cellpadding='0' cellspacing='0' bgcolor='#ffffff' style='border: 1px solid #d4a574; border-radius: 8px;'>
                        <!-- HEADER WITH LOGO -->
                        <tr>
                            <td align='center' style='padding: 30px 20px; border-bottom: 3px solid #8B6F47;'>
                                <p style='margin: 0 0 15px 0; font-size: 14px; color: #8B6F47; font-weight: 600; letter-spacing: 2px;'>🕯️ TASTE OF BIHAR 🕯️</p>
                            </td>
                        </tr>
                        
                        <!-- MAIN CONTENT -->
                        <tr>
                            <td style='padding: 40px 30px;'>
                                <!-- TITLE -->
                                <h2 style='margin: 0 0 10px 0; font-size: 26px; color: #8B6F47; text-align: center; font-weight: 700;'>Security Verification</h2>
                                <p style='margin: 0 0 25px 0; font-size: 14px; color: #a89070; text-align: center;'>Password Reset Code</p>
                                
                                <!-- INTRODUCTION -->
                                <p style='margin: 0 0 25px 0; font-size: 15px; color: #333; line-height: 1.6; text-align: center;'>We received a request to reset your password. Use the code below to complete the process:</p>
                                
                                <!-- OTP CODE BOX -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='margin: 30px 0; border: 2px solid #8B6F47; background-color: #faf7f2; border-radius: 6px;'>
                                    <tr>
                                        <td align='center' style='padding: 30px 20px;'>
                                            <p style='margin: 0; font-size: 12px; color: #888; margin-bottom: 15px;'>Your One-Time Password</p>
                                            <h1 style='margin: 0; font-size: 48px; color: #8B6F47; font-weight: 900; letter-spacing: 6px; font-family: monospace;'>$otp</h1>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- EXPIRY WARNING -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='margin: 20px 0; background-color: #fffbf0; border-left: 4px solid #8B6F47; border-radius: 4px;'>
                                    <tr>
                                        <td style='padding: 15px 18px;'>
                                            <p style='margin: 0; font-size: 13px; color: #6b5a3d; font-weight: 600;'>⏱️ Valid for 15 minutes</p>
                                            <p style='margin: 5px 0 0 0; font-size: 12px; color: #666;'>This code expires after one use or after 15 minutes, whichever comes first.</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- SECURITY NOTICE -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f5f5f5; border-radius: 4px; margin-top: 20px;'>
                                    <tr>
                                        <td style='padding: 15px 18px;'>
                                            <p style='margin: 0; font-size: 12px; color: #333;'><strong>🔒 Security Notice:</strong> If you didn't request this code, please ignore this email or contact our support team immediately.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- FOOTER -->
                        <tr>
                            <td align='center' style='padding: 25px 30px; border-top: 1px solid #e8dcc8; font-size: 12px; color: #999;'>
                                <p style='margin: 0 0 5px 0;'><span style='color: #8B6F47; font-weight: 600;'>Taste of Bihar</span> | Premium Handcrafted Candles</p>
                                <p style='margin: 0;'>© 2026 All rights reserved. | Security Team</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        ";

        $mail->AltBody = "Your OTP is: $otp";

        $mail->send();

        return ['success' => true, 'message' => 'OTP sent'];

    } catch (Exception $e) {
        error_log('Mail Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Email failed'];
    }
}

/* ================= CREATE REQUEST ================= */
function create_password_reset_request($email) {
    global $conn;

    $email = strtolower(trim($email));

    $sql = "SELECT id, first_name FROM users WHERE LOWER(email)=LOWER('$email') LIMIT 1";
    $res = mysqli_query($conn, $sql);

    if(mysqli_num_rows($res) === 0){
        return [
            'success' => true,
            'message' => 'If email exists, OTP sent.',
            'found' => false
        ];
    }

    $user = mysqli_fetch_assoc($res);

    $otp = generate_otp();
    $otp_hash = hash('sha256', $otp);
    $expiry = date('Y-m-d H:i:s', time()+900);

    // delete old
    mysqli_query($conn, "DELETE FROM password_resets WHERE LOWER(email)=LOWER('$email')");

    // insert new
    mysqli_query($conn, "INSERT INTO password_resets (user_id,email,otp_hash,expires_at,is_used)
    VALUES ({$user['id']},'$email','$otp_hash','$expiry',FALSE)");

    // send email
    send_otp_email($email, $otp, $user['first_name']);

    return [
        'success'=>true,
        'found'=>true,
        'message'=>'OTP sent successfully',
        'email'=>substr($email,0,3).'***'.substr($email,strrpos($email,'@'))
    ];
}

/* ================= VERIFY OTP (FIXED) ================= */
function verify_password_reset_otp($email, $otp) {
    global $conn;

    $email = strtolower(trim($email));
    $otp = preg_replace('/\D/', '', trim($otp));

    if(strlen($otp) !== 6){
        return ['success'=>false,'message'=>'Invalid OTP format'];
    }

    // get latest OTP
    $sql = "SELECT id,user_id,otp_hash,expires_at,is_used 
            FROM password_resets 
            WHERE LOWER(email)=LOWER('$email')
            ORDER BY created_at DESC LIMIT 1";

    $res = mysqli_query($conn,$sql);

    if(!$res || mysqli_num_rows($res)==0){
        return ['success'=>false,'message'=>'Invalid or expired OTP'];
    }

    $row = mysqli_fetch_assoc($res);

    if($row['is_used']){
        return ['success'=>false,'message'=>'OTP already used'];
    }

    if(strtotime($row['expires_at']) < time()){
        return ['success'=>false,'message'=>'OTP expired'];
    }

    $entered_hash = hash('sha256',$otp);

    if($entered_hash !== $row['otp_hash']){
        return ['success'=>false,'message'=>'Invalid or expired OTP'];
    }

    // generate token
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256',$token);

    mysqli_query($conn,"UPDATE password_resets 
        SET is_used=TRUE,
            verification_token='$token_hash',
            verified_at=NOW()
        WHERE id={$row['id']}");

    return [
        'success'=>true,
        'message'=>'OTP verified',
        'verification_token'=>$token,
        'user_id'=>$row['user_id']
    ];
}

/* ================= RESET PASSWORD ================= */
function reset_user_password($token,$pass,$confirm){
    global $conn;

    if($pass!==$confirm){
        return ['success'=>false,'message'=>'Passwords do not match'];
    }

    if(strlen($pass)<6){
        return ['success'=>false,'message'=>'Password too short'];
    }

    $token_hash = hash('sha256',$token);

    $sql="SELECT user_id FROM password_resets 
          WHERE verification_token='$token_hash'
          AND verified_at > DATE_SUB(NOW(),INTERVAL 30 MINUTE)
          LIMIT 1";

    $res=mysqli_query($conn,$sql);

    if(mysqli_num_rows($res)==0){
        return ['success'=>false,'message'=>'Invalid session'];
    }

    $user=mysqli_fetch_assoc($res);

    $hash=password_hash($pass,PASSWORD_BCRYPT);

    mysqli_query($conn,"UPDATE users SET password='$hash' WHERE id={$user['user_id']}");

    return ['success'=>true,'message'=>'Password updated'];
}