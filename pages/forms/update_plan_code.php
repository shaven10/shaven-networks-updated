<?php
session_start();
if(!isset($_SESSION['usr_id']))
{
    header("location:login.php");
}
$sessionID=$_SESSION['usr_id'];



include('../../dbconnect.php');
    $plan_id= $_POST['plan_id'];
      $pid= $_POST['sid'];

  
  $sql = "UPDATE tbl_subs_profile set plan_id='$plan_id' WHERE id='$pid'";
  $result = $conn->query($sql);
  
   echo '<script>
              alert("Plan Update Successfull!!!");
              window.location = "'.$SITE_URL.'../tables/reports.php"
              </script>';
  $conn->close();
 // window.location = "'.$SITE_URL.'../tables/reports.php"
  
  ?>