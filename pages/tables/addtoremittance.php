<?php

session_start();

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


  $sql1 = "SELECT * FROM tbl_remittances where paymentid='$pid'";

  $result1 = $conn->query($sql1);

  if ($result1->num_rows > 0) {

   
//do no insert 
    

  }else{



                                          //add to colektahonon table
                                          $sql = "INSERT INTO tbl_remittances (paymentid,dt)
                                          VALUES ('$pid',now());";
                                          if ($conn->multi_query($sql) === TRUE) {
                                            // echo '<script> alert("New records created successfully");</script>'; 
                                            } else {
                                                echo "Error: " . $sql . "<br>" . $conn->error;
                                                }
                               


                                      //END
                                    }
$conn->close();





//window.location = "'.$SITE_URL.'collections.php"

?>