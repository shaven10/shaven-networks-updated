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



$sql = "UPDATE tbl_payments set revert='',status='0', postedby='$sessionID',payment_date=now() WHERE id='$pid'";

$result = $conn->query($sql);

  //delete to remittance table
  $sql1 = "DELETE from tbl_remittances where paymentid='$pid'";
  $result1 = $conn->query($sql1);



//END

 echo '<script>

            alert("Request for revert approved.");

            window.location = "'.$SITE_URL.'collections_revert_approval.php"

            </script>';

$conn->close();





//window.location = "'.$SITE_URL.'collections.php"

?><?php

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



$sql = "UPDATE tbl_payments set revert='',status='0', postedby='$sessionID',payment_date=now() WHERE id='$pid'";

$result = $conn->query($sql);



 echo '<script>

            alert("Request for revert approved.");

            window.location = "'.$SITE_URL.'collections_revert_approval.php"

            </script>';

$conn->close();





//window.location = "'.$SITE_URL.'collections.php"

?><?php

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



$sql = "UPDATE tbl_payments set revert='',status='0', postedby='$sessionID',payment_date=now() WHERE id='$pid'";

$result = $conn->query($sql);



 echo '<script>

            alert("Request for revert approved.");

            window.location = "'.$SITE_URL.'collections_revert_approval.php"

            </script>';

$conn->close();





//window.location = "'.$SITE_URL.'collections.php"

?><?php

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



$sql = "UPDATE tbl_payments set revert='',status='0', postedby='$sessionID',payment_date=now() WHERE id='$pid'";

$result = $conn->query($sql);



 echo '<script>

            alert("Request for revert approved.");

            window.location = "'.$SITE_URL.'collections_revert_approval.php"

            </script>';

$conn->close();





//window.location = "'.$SITE_URL.'collections.php"

?>