<?php

/* 
    composer require spomky-labs/otphp
    composer require endroid/qr-code
 */
require '../vendor/autoload.php';
include("../apanel/connect.php");
include("./include/functions.php");
include("../include/notification.class.php");
include("../sql-injection/sql-injection.php");

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PDO;


/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('display_errors', '1'); */

$company_id     = $_SESSION[SESS_PRE . '_ADMIN_COMPANY'];
$user_id        = $_SESSION[SESS_PRE . '_ADMIN_SESS_ID'];
$curr_user_role = $_SESSION[SESS_PRE . '_ADMIN_ROLE'];
$is_super_admin = $_SESSION[SESS_PRE . '_IS_SUPER_ADMIN'];

$userInfo = $db->rpGetSingleRecord('admin', '*', "isDelete=0 and active_account=1 and id='" . $user_id . "'");

if (!$userInfo) {
    die('User not found.');
}

// For testing purposes, use a default key
$otp_secret = $userInfo['otp_secret'] ?? 'dgdfklgf45gsf5dsf';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$otp_secret) {
        die('OTP secret not found.');
    }
    
    /* 
        echo 'Secret: ' . $otp_secret . '<br>';
        echo 'OTP Code: ' . $otpCode . '<br>';
        echo 'Generated OTP: ' . $totp->now() . '<br>';
    */
    $totp = TOTP::create($otp_secret);
    $otpCode = trim($_POST['otp']);
    
    if ($totp->verify($otpCode)) {
        echo 'OTP is valid.';
    } else {
        echo 'Invalid OTP.';
    }
} else {
    if (!$userInfo['otp_secret']) {
        $totp = TOTP::create();
        $otp_secret = $totp->getSecret();
        $totp->setLabel('Sukuma-' . $userInfo['email']);
        $totp->setIssuer('Sukuma');
        $provisioningUri = $totp->getProvisioningUri();

        $db->rpupdate('admin', ['otp_secret' => $otp_secret], 'id=' . $user_id);

    } else {
        $totp = TOTP::create($otp_secret);
        $totp->setLabel('Sukuma-' . $userInfo['email']); 
        $totp->setIssuer('Sukuma');
        $provisioningUri = $totp->getProvisioningUri();
    }

    $qrCode = new QrCode($provisioningUri);
    $qrCode->setSize(300);
    $qrCode->setMargin(10);

    $writer = new PngWriter();
    $qrCodeData = $writer->write($qrCode)->getString();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication</title>
</head>
<body>
    <h1>Two-Factor Authentication</h1>
    <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ?>
        <h2>Scan the QR Code</h2>
        <img src="data:image/png;base64,<?= base64_encode($qrCodeData) ?>" alt="QR Code">
        <p>Use your authenticator app to scan the QR code above.</p>
    <?php } ?>
    <h2>Enter OTP Code</h2>
    <form method="post">
        <input type="text" name="otp" required>
        <button type="submit">Verify OTP</button>
    </form>
</body>
</html>
