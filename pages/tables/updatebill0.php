<?php
require_once __DIR__ . '/../../includes/guard.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if(!isset($_SESSION['usr_id']))
{
	header("location:login.php");
}
$sessionID=$_SESSION['usr_id'];
?>
<?php
  $pid= $_GET['payment_id'];
  include('../../dbconnect.php');

$sql = "UPDATE tbl_payments set status='0', postedby='$sessionID',payment_date=now() WHERE id='$pid'";
$result = $conn->query($sql);

 echo '<script>
            alert("Payment Posted Successfully!!!");
            window.location = "'.$SITE_URL.'collections.php"
            </script>';
$conn->close();


//window.location = "'.$SITE_URL.'collections.php"
?>