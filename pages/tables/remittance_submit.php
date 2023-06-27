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



$sql = "UPDATE tbl_remittances set remit='1' WHERE paymentid='$pid'";

$result = $conn->query($sql);



 echo '<script>

            alert("Collection Recieve");

            window.location = "'.$SITE_URL.'remittances.php"

            </script>';

$conn->close();





//window.location = "'.$SITE_URL.'collections.php"

?>