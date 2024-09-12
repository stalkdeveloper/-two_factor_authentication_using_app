<?php

require '../vendor/autoload.php';
include("../apanel/connect.php");
include("./include/functions.php");
include("../include/notification.class.php");
include("../sql-injection/sql-injection.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$company_id     = $_SESSION[SESS_PRE . '_ADMIN_COMPANY'];
$user_id        = $_SESSION[SESS_PRE . '_ADMIN_SESS_ID'];
$curr_user_role = $_SESSION[SESS_PRE . '_ADMIN_ROLE'];
$is_super_admin = $_SESSION[SESS_PRE . '_IS_SUPER_ADMIN'];

$userInfo = $db->rpGetSingleRecord('admin', '*', "isDelete=0 and active_account=1 and id='" . $user_id . "'");

$totp = TOTP::create();
$secret = $totp->getSecret();
$totp->setLabel(SITETITLE . '-' . $userInfo['email']);
$totp->setIssuer(SITETITLE);
$provisioningUri = $totp->getProvisioningUri();

// $db->rpupdate('admin', ['otp_secret' => $secret], 'id=' . $user_id);

// Generate QR Code
$qrCode = new QrCode($provisioningUri);
$qrCode->setSize(300); 
$qrCode->setMargin(10);

$writer = new PngWriter();
$qrCodeData = $writer->write($qrCode)->getString();

// Output the QR Code
header('Content-Type: image/png');
echo $qrCodeData;

?>
