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

  $pid= $_GET['uid'];
  include('../../dbconnect.php');

$sql = "UPDATE tbl_subs_profile set status='1' WHERE id='$pid'";
$result = $conn->query($sql);

 echo '<script>
            alert("Subscriber Activated!!!");
            window.location = "'.$SITE_URL.'reports.php"
            </script>';
$conn->close();


//window.location = "'.$SITE_URL.'collections.php"
?>