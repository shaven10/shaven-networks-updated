<?php
require_once __DIR__ . '/includes/guard.php';
?>
                <?php
           
               
               
                $ssid=$_SESSION['usr_id'];

                        include('dbconnect.php');
                         $sql = "select sum(payment_amount) as wallet from tbl_payments as a,tbl_remittances as b where a.id=b.paymentid and postedby='$ssid' and remit='0';";

                     

                        $result = $conn->query($sql);

                     while($row = $result->fetch_assoc()) {

                        echo $wallet=bcadd($row['wallet'],'0',2);

                         

                              }


                                  $conn->close();

                              ?>