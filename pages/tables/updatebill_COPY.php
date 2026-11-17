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



    $datenow=date_create('Asia/Taipei');

   $PaymentTimestamp= date_format($datenow,"Y-m-d H:i:s");



  $pid= $_GET['payment_id'];

  include('../../dbconnect.php');



$sql = "UPDATE tbl_payments set status='1', postedby='$sessionID',payment_date='$PaymentTimestamp' WHERE id='$pid'";

$result = $conn->query($sql);



 echo '<script>

            alert("Payment Posted Successfully!!!");

            window.location = "'.$SITE_URL.'collections.php"

            </script>';

$conn->close();




include('addtoremittance.php');
//window.location = "'.$SITE_URL.'collections.php"

?>