<?php
include("../apanel/connect.php");
include("./include/functions.php");
include("../include/notification.class.php");
include("../sql-injection/sql-injection.php");

use OTPHP\TOTP;
use PDO;

$company_id     = $_SESSION[SESS_PRE . '_ADMIN_COMPANY'];
$user_id        = $_SESSION[SESS_PRE . '_ADMIN_SESS_ID'];
$curr_user_role = $_SESSION[SESS_PRE . '_ADMIN_ROLE'];
$is_super_admin = $_SESSION[SESS_PRE . '_IS_SUPER_ADMIN'];

$userInfo = $db->rpGetSingleRecord('admin', '*', "isDelete=0 and active_account=1 and id='" . $user_id . "'");

if (!$userInfo || empty($userInfo['otp_secret'])) {
    die('OTP secret not found.');
}

$otp_secret = $userInfo['otp_secret'];

$totp = TOTP::create($otp_secret);

if (!isset($_POST['otp'])) {
    die('OTP code not provided.');
}

$otpCode = trim($_POST['otp']);

if ($totp->verify($otpCode)) {
    echo 'OTP is valid.';
} else {
    echo 'Invalid OTP.';
}
?>
