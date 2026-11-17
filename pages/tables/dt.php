<?php
require_once __DIR__ . '/../../includes/guard.php';
?>
<!DOCTYPE html>
<html>
<body>

<?php 

 echo date("m-d-Y H:i:s");
 echo '<br>';
 //date of today
  $datenow=date_create('Asia/Taipei');
  echo $PaymentTimestamp= date_format($datenow,"m-d-Y H:i:s");


   echo '<br>';
    
   echo now();
?>

</body>
</html>