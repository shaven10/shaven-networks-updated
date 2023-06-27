<?php

session_start();

if(!isset($_SESSION['usr_id']))

{

	header("location:login.php");

}

$sessionID=$_SESSION['usr_id'];

?>

<?php



  $pid= $_GET['payment_id'];

  include('../../dbconnect.php');



$sql = "UPDATE tbl_payments set revert='1',status='1', postedby='$sessionID',payment_date=now() WHERE id='$pid'";

$result = $conn->query($sql);



 echo '<script>

            alert("Payment Revert Successfully!!! NOTE: waiting for admin approval.");

            window.location = "'.$SITE_URL.'collections_revert.php"

            </script>';

$conn->close();





//window.location = "'.$SITE_URL.'collections.php"

?>