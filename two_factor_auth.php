<?php

require '../vendor/autoload.php';
include("../sql-injection/sql-injection.php");

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$si   = new DB;

$userInfo           = $db->rpGetSingleRecord('admin', '*', "isDelete=0 and active_account=1 and email='" . base64_decode($_GET['token']) . "'");
$userSettingInfo    = $db->rpGetSingleRecord('admin_settings', '*', "isDelete=0 AND admin_id='" . $userInfo['id'] . "'");
$curr_user_role     = $userInfo['role'];


$userCompany = $db->rpgetValue('company', 'is_two_factor_enabled', "isDelete=0 AND id='" . $userInfo['company_id'] . "'");
$isTwoFactorEnabled     = !empty($userCompany) && $userCompany > 0 && $userCompany == 1 ? 1 : 0;
$isTwoFactorDisabled    = !empty($userCompany) && $userCompany == 0 ? 0 : 1;


if ($curr_user_role != 0 && $curr_user_role != 3) {
    $authStatus = $isTwoFactorEnabled ? 1 : ($isTwoFactorDisabled ? 0 : null);
    if ($authStatus !== null) {
        $si->update('admin_settings', ['is_authenticate_enabled' => $authStatus], ['%i'], ['admin_id'=>$userInfo['id']], ['%i']);
        if($authStatus == 0){
            $si->update('admin_settings', ['is_authenticate' => 0, 'otp_secret' => null], ['%i', '%s'], ['admin_id'=>$userInfo['id']], ['%i']);
            
            /* Store Data in the Session */
            $db->checkLoginWillPassOrNot($userInfo, $session=true);
            /* Direct Login If Company not enable two step authenticate */
            $_SESSION['MSG'] = 'Success_login';
            $db->rplocation(ADMINURL."dashboard/");
        }
    }
}

// print_r($userSettingInfo);
$otp_secret = $userSettingInfo['otp_secret'] ?? null;
$isAuthenticate = $userSettingInfo['is_authenticate'] ?? 0;
$isAuthenticateEnabled = $userSettingInfo['is_authenticate_enabled'] ?? 0;

if (($curr_user_role == 0 || $curr_user_role == 3) && $isAuthenticateEnabled == 0) {
    /* Store Data in the Session */
    $db->checkLoginWillPassOrNot($userInfo, $session=true);
    $_SESSION['MSG'] = 'Success_login';
    $db->rplocation(ADMINURL."dashboard/");
}

$qrCodeData = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$otp_secret) {
        $_SESSION['MSG'] = 'Try Again, OTP secret not found.';
        $db->rplocation(SITEURL."account-verification?token=".base64_encode($userInfo['email']));
    }
    
    $totp = TOTP::create($otp_secret);
    $otpCode = trim($_POST['otp']);
    
    if ($totp->verify($otpCode)) {
        $si->update('admin_settings', ['is_authenticate' => 1], ['%i'], ['admin_id'=>$userInfo['id']], ['%i']);
        /* Store Data in the Session */
        $db->checkLoginWillPassOrNot($userInfo, $session=true);

        $_SESSION['MSG'] = 'Success_login';
        $db->rplocation(ADMINURL."dashboard/");
    } else {
        $_SESSION['MSG'] = 'Invalid OTP.';
        $db->rplocation(SITEURL."account-verification?token=".base64_encode($userInfo['email']));
    }
} else {
    if (!$userSettingInfo['otp_secret']) {
        $totp = TOTP::create();
        $otp_secret = $totp->getSecret();
        $totp->setLabel($userInfo['email']);
        $totp->setIssuer('Sukuma');
        $provisioningUri = $totp->getProvisioningUri();

        $si->update('admin_settings', ['otp_secret' => $otp_secret], ['%s'], ['admin_id'=>$userInfo['id']], ['%i']);
    } else {
        $totp = TOTP::create($otp_secret);
        $totp->setLabel($userInfo['email']); 
        $totp->setIssuer('Sukuma');
        $provisioningUri = $totp->getProvisioningUri();
    }

    $qrCode = new QrCode($provisioningUri);
    $qrCode->setSize(300);
    $qrCode->setMargin(10);

    $writer = new PngWriter();
    $qrCodeData = $writer->write($qrCode)->getString();

    $_SESSION[SESS_PRE.'_IS_AUTH_ENABLED'] = $isAuthenticateEnabled ?? 0;
    $_SESSION[SESS_PRE.'_IS_AUTH_ENABLED_EMAIL'] = $userInfo['email'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication</title>
    <link rel="stylesheet" href="<?php echo ADMINURL; ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@100..900&display=swap" rel="stylesheet">
    <style>
        *{
            box-sizing: border-box;
        }
        body{
            background-color: #f1f2f2;
            padding: 50px !important;
            margin: 0;
            font-family: "Lexend", sans-serif;
        }
        /* .scanner-page{
            max-width: 650px;
            margin: 0 auto;
            background-color: #ffeaea;
            border-radius: 10px;
            padding: 30px;
        } */
        .scanner-inner{
            position: relative;
            z-index: 1;
            max-width: 360px;
            width: 100%;
            margin: 0 auto;
        }
        .card-scanner{
            background-color: #ebf3ff;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 0 14px rgba(0, 0, 0, 0.2);
            -webkit-box-shadow: 0 0 14px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        .card-scanner .brand-logo{
            margin-bottom: 20px;
        }
        .scanner-inner::after{
            content: "";
            position: absolute;
            left: 6px;
            bottom: 0;
            height: 40px;
            border-radius: 10px;
            background: #000;
            transform: translate(0, -20%) rotate(-7deg);
            transform-origin: center center;
            box-shadow: 0 0 20px 15px rgba(0, 0, 0, 0.2);
            -webkit-box-shadow: 0 0 20px 15px rgba(0, 0, 0, 0.2);
            z-index: -1;
            filter: blur(10px);
            -webkit-filter: blur(10px);
            opacity: 0.7;
            width: 80%;
        }
        .scanner-inner::before{
            content: "";
            position: absolute;
            right: 6px;
            bottom: 0;
            height: 40px;
            border-radius: 10px;
            background: #000;
            transform: translate(0, -20%) rotate(7deg);
            transform-origin: center center;
            box-shadow: 0 0 20px 15px rgba(0, 0, 0, 0.2);
            -webkit-box-shadow: 0 0 20px 15px rgba(0, 0, 0, 0.2);
            z-index: -1;
            filter: blur(10px);
            -webkit-filter: blur(10px);
            opacity: 0.7;
            width: 80%;
        }
        .scanner-inner p{
            margin-bottom: 0;
            font-size: 12px;
            color: #555;
        }
        .scanner-img{
            background-color: #fff;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
        }
        .scanner-img img{
            max-width: 240px;
            width: 100%;
        }
        .scanner-img h2{
            margin-top: 5px;
            margin-bottom: 10px;
            color: #2b3ec1;
            font-size: 20px;
        }
        .otp-block{
            margin-top: 40px;
        }
        .otp-block label{
            font-weight: 600;
            padding-bottom: 5px;
            display: block;
            text-align: left;
        }
        .otp-block form{
            background-color: #ffffff;
            border-radius: 5px;
            display: flex;
            display: -webkit-flex;
            flex-wrap: wrap;
            -webkit-flex-wrap: wrap;
            padding: 5px;
        }
        .otp-block form input{
            flex: 0 0 calc(100% - 100px);
            -webkit-flex: 0 0 calc(100% - 100px);
            max-width: calc(100% - 100px);
            background-color: transparent;
            border: none;
            padding: 5px 10px;
            outline: none;
            width: 100%;
        }
        .otp-block form input:focus{
            outline: none;
        }
        .otp-block form button{
            background-color: #000;
            color: #fff;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            padding: 5px 12px;
            flex: 0 0 100px;
            -webkit-flex: 0 0 100px;
            max-width: 100px;
            width: 100%;
            font-size: 14px;
        }
        #otp-form { display: none; }
        #scan-confirmation { 
            margin-top: 20px; 
            text-align: center;
        }

        .common-button{
            margin-top: 20px; 
            text-align: center;
        }

        .confirm-btn{
            background-color: #2b3ec1;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .confirm-btn:hover {
            background-color: #1a2c8b;
        }

        .confirm-btn:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.3);
        }
        .instructions{
            padding: 0;
            margin: 10px 0 0 18px;
        }
        .instructions li{
            font-size: 12px;
            line-height: 1.5;
            text-align: left;
            color: #222;
        }
        .note {
            font-size: 12px;
            color: #555;
            text-align: left;
            margin-top: 5px;
        }

        @media screen and (max-width: 767px){
            body{
                padding: 20px !important;
            }
            .card-scanner{
                padding: 15px;
            }
            .scanner-img{
                padding: 10px;
            }
            .scanner-img img{
                max-width: 100%;
            }
        }
    </style>
    <!-- Include jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
    <div class="scanner-page">
        <?php if(isset($isAuthenticate) && ($isAuthenticate == 0)){ ?>
            <div class="scanner-inner">
                <div class="card-scanner">
                    <div class="brand-logo">
                        <img src="<?= ADMINURL; ?>assets/images/logo.png" height="60">
                        <span class="text-dark" style="font-size: 25px; font-weight: bold;">
                            <br><?= SITETITLE; ?>
                        </span>
                    </div>
                    <?php if ($qrCodeData) { ?>
                        <div class="scanner-img" id="qr-code-container">
                            <h2>Scan the QR Code</h2>
                            <img src="data:image/png;base64,<?= base64_encode($qrCodeData) ?>" alt="QR Code">
                            <p>Use your authenticator app to scan the QR code above.</p>
                        </div>
                    <?php } ?>
                    <div id="scan-confirmation">
                        <button id="confirm-scan" class="confirm-btn">I have scanned the QR Code</button>
                    </div>
                    <div class="otp-block" id="otp-form">
                        <label>Enter OTP Code</label>
                        <form method="post">
                            <input type="text" name="otp" placeholder="Enter OTP" required>
                            <button type="submit">Verify OTP</button>
                        </form>
                        <p class="note">Note: The OTP code changes every 30 seconds. Enter it promptly.</p>
                    </div>
                </div>
            </div>
            
        <?php } else if(isset($isAuthenticate) && ($isAuthenticate == 1) && ($isAuthenticate > 0)) { ?>
            <div class="scanner-inner">
                <div class="card-scanner">
                    <div class="brand-logo">
                        <img src="<?= ADMINURL; ?>assets/images/logo.png" height="60">
                        <span class="text-dark" style="font-size: 25px; font-weight: bold;">
                            <br><?= SITETITLE; ?>
                        </span>
                    </div>
                    <?php if ($qrCodeData) { ?>
                        <!-- <div class="scanner-img scanner-img-for-existing" id="qr-code-container" style="display:none;">
                            <h2>Scan the QR Code</h2>
                            <img src="data:image/png;base64,<?= base64_encode($qrCodeData) ?>" alt="QR Code">
                            <p>Use your authenticator app to scan the QR code above.</p>
                        </div> -->
                    <?php } ?>
                    <!-- <div class="common-button" id="generate-qr-code">
                        <button id="show-qr-code" class="confirm-btn">Generate QR Code</button>
                    </div> -->
                    <div class="scan-confirmation-for-existing common-button" id="scan-confirmation-existing" style="display:none;">
                        <button id="confirm-scan" class="confirm-btn">I have scanned the QR Code</button>
                    </div>
                    <div class="otp-block" id="otp-form-existing">
                        <label>Enter OTP from Authenticator</label>
                        <form method="post">
                            <input type="text" name="otp" placeholder="Enter OTP" required>
                            <button type="submit">Verify OTP</button>
                        </form>
                        <ul class="instructions">
                            <li>Open your Google or Microsoft Authenticator app.</li>
                            <li>Locate the account labeled <strong>"Sukuma: <?= htmlspecialchars($userInfo['email']); ?>"</strong>.</li>
                            <li>Enter the 6-digit code from your app into the OTP field below.</li>
                        </ul>
                        <p class="note">Note: The OTP code changes every 30 seconds. Enter it promptly.</p>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>


    <script>
        $(document).ready(function() {
            $('#confirm-scan').click(function() {
                $(this).hide();
                $('#otp-form').fadeIn();
                $('.scanner-img').hide();
                $('#otp-form-existing').show();
            });

            $('#show-qr-code').click(function() {
                $(this).hide();
                $("#qr-code-container").show();
                $(".scan-confirmation-for-existing").show();
                $(".instructions").hide();
                $('#otp-form-existing').hide();
            });
        });

    </script>
    <script src="<?php echo ADMINURL; ?>assets/js/bootstrap-notify.js"></script>
    <script>
		$(document).ready(function(e){
			setTimeout(function(){
				<?php if(isset($_SESSION['MSG']) && !empty($_SESSION['MSG']) ) { 
					$msg_type ='danger';
					if(isset($_SESSION['msg_type'])){
						$msg_type = $_SESSION['msg_type'];
					}
					unset($_SESSION['msg_type']);
					?>
					$.notify({message: "<?php echo $_SESSION['MSG']; ?>"},{type: '<?= $msg_type ?>'});
				<?php unset($_SESSION['MSG']); } 
				?>
			},1000);			
		})
	</script>
</body>
</html>
