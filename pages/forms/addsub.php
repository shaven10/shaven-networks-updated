<?php

   $s_name= ucwords($_POST['s_name']);
  $s_address= ucwords($_POST['s_address']);
  $plan_id= $_POST['plan_id'];
  $medium= $_POST['medium'];
  $s_doi= $_POST['s_doi'];
  include('../../dbconnect.php');
$sql = "SELECT * FROM tbl_subs_profile";
$result = $conn->query($sql);


$sql = "SELECT * FROM tbl_subs_profile WHERE s_name='$s_name'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo '<script>
            alert("Already Exist!!!");
            window.location = "'.$SITE_URL.'sub.addnew.php"
            </script>';
  //  echo "Already Exist!!!";
} else {
    echo "0 results";
    //proceed to next code for saving
        //code sa insert data
            $sql = "INSERT INTO tbl_subs_profile (status,medium,s_name, s_address, plan_id,s_doi,timestamp)
                VALUES ('0','$medium','$s_name', '$s_address', '$plan_id', '$s_doi',now());";
            if ($conn->multi_query($sql) === TRUE) {
           // echo "New records created successfully";
            //header("location: sub.addnew.php");

            echo '<script>
            alert("New records created successfully");
            window.location = "'.$SITE_URL.'sub.addnew.php"
            </script>';


            } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
            }
        //end sa code insert data

}

$conn->close();
?>