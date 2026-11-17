<?php
require_once __DIR__ . '/includes/guard.php';
?>
                <?php
                        include('dbconnect.php');
                   //     $sql = "select sum(payment_amount) as summary,concat(payment_month,', ',payment_year) as due from tbl_payments where status<>0 group by payment_month,payment_year";
                        $sql = "select sum(payment_amount) as summary,payment_month as due from tbl_payments as a,tbl_months as b where a.payment_month=b.month_name and status<>0 group by payment_month,payment_year order by b.id asc;";
                     
                        $result = $conn->query($sql);

                        $summMonth = array();
                        $summAmount = array();
                        while($row = $result->fetch_assoc()) {
                            $summMonth[] = $row;
                            $summAmount[] = $row;
                         }
                         
                         foreach($summMonth as $ro){
                             echo "'".$ro['due']."'".", ";
                         }
                         foreach($summAmount as $ro1){
                             $ro1['summary'].", ";
                        }
                     //   echo "'January', 'February', 'March',";




                    /*    while($row = $result->fetch_assoc()) {
                         $summary=$row['due'];
                       echo  $summary=$summary.' ,';
                          $due=$row['due'];
                         
                              } */
                              
                                  $conn->close();
                              ?>