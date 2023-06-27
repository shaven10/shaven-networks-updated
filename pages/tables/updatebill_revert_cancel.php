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

$sql = "UPDATE tbl_payments set revert='' WHERE id='$pid'";
$result = $conn->query($sql);

 echo '<script>
            alert("Revert Request Cancelled!!!");
            window.location = "'.$SITE_URL.'collections_revert.php"
            </script>';
$conn->close();


//window.location = "'.$SITE_URL.'collections.php"
?>