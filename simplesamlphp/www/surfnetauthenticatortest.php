<?php

require_once('../lib/_autoload.php');
//include_once "/libis/CA/production/tools/simplesamlphp/lib/_autoload.php";

$authenticatorurl = "https://saml.collectiveaccess.tudelft.nl/surfnetauthenticatortest.php";
$serivceurl = "https://test.collectiveaccess.tudelft.nl/ca_tudelft_test/index.php/system/Auth/DoLogin";
try {
    // Init SP instance
    $as = new SimpleSAML_Auth_Simple('default-sp');    // Init SP instance
    // Process login action. Login function of your SP uses ...?action=login
    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'login' ) {
        $as->login( array(
            'ReturnTo' => $authenticatorurl,
            'ForceAuthn' => false,
        ) );
        exit;
    }

    //returning from surfnet, check if user is authenticated, if yes, prepare login form and post it to service (collective access)
    if ($as->isAuthenticated()) {
        $userdata = array();
        $attributes = $as->getAttributes();
        $useremail = $attributes['urn:mace:dir:attribute-def:mail'];
        if(is_array($useremail))
            $useremail = current($useremail);

        $usersname = $attributes['urn:mace:dir:attribute-def:sn'];
        $usergivenname = $attributes['urn:mace:dir:attribute-def:givenName'];

        $userdata['sname'] = (is_array($usersname))? current($usersname):$usersname;
        $userdata['givenname'] = (is_array($usergivenname))? current($usergivenname):$usergivenname;
?>

        <form id="surfForm"
              action="<?php echo $serivceurl;?>"

              method="post">
            <input type="hidden" name="username" value=<?php echo $useremail; ?>>
	    <input type="hidden" name="password" value=<?php echo "f1960a90f1fbdbf33ee56acb08715f793c1ec83e9e0902225fba41b606e893d1".serialize($userdata); ?>
        </form>
        <script type="text/javascript">
            document.getElementById('surfForm').submit();
        </script>
<?php
    } else { // user is not authenticated, return to login page of the service (collective access)
        echo "<meta http-equiv='refresh' content='0; url=".$serivceurl."'>";
    }
}
catch (Exception $e)
{
    echo $e->getFile().':'.$e->getLine().' : '.$e->getMessage();
    echo "<meta http-equiv='refresh' content='0; url=".$serivceurl."'>";
}
