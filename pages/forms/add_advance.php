
<?php
session_start();
if(!isset($_SESSION['usr_id']))
{
	header("location:login.php");
}
$sessionID=$_SESSION['usr_id'];
?>
<?php
//date of today
$datenow=date_create('Asia/Taipei');
$DateofToday= date_format($datenow,"m-d-Y");
$DofToday= date_format($datenow,"d");
$MonthofToday= date_format($datenow,"F");
$YearofTOday= date_format($datenow,"Y");
//end
 
 
  $p_month= $_POST['p_month'];
  $sid= $_POST['sid'];
    $p_amount= $_POST['p_amount'];
  $p_day= $_POST['p_day'];

  include('../../dbconnect.php');
 $datenow=date_create('Asia/Taipei');
   $PaymentTimestamp= date_format($datenow,"Y-m-d H:i:s");
  
$sql = "INSERT INTO tbl_payments (payment_year,postedby,status,payment_date,payment_status,payment_amount,payment_day,payment_month,payment_uid,p_timestamp)
  VALUES ('$YearofTOday','$sessionID','1',now(),'2','$p_amount','$p_day','$p_month','$sid',now());";
 if ($conn->multi_query($sql) === TRUE) {
    echo '<script>
            alert("Advace Payment Sucessfull");
            window.location = "'.$SITE_URL.'../tables/allsubs.php"
            </script>';
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
        }

  //add to remittance table

  $sql = "SELECT * FROM tbl_payments where payment_status='2' order by id desc LIMIT 1";
  $result = $conn->query($sql);
  while($row = $result->fetch_assoc()) {
    $id2save=$row['id'];
  } 


  $sql = "INSERT INTO tbl_remittances (paymentid,dt)
  VALUES ('$id2save',now());";
  if ($conn->multi_query($sql) === TRUE) {
    // echo '<script> alert("New records created successfully");</script>'; 
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
        }



//END


$conn->close();
?>