<?php
require_once __DIR__ . '/includes/guard.php';
?>
                <?php
                        include('dbconnect.php');
                      $sql = "select count(*) as no,status from tbl_subs_profile group by status;;";
                     
                        $result = $conn->query($sql);

                        $numberofSubs = array();
                        $stat = array();
                        while($row = $result->fetch_assoc()) {
                            $numberofSubs[] = $row;
                            $stat[] = $row;
                         }
                         
                         foreach($numberofSubs as $ro){
                              echo $ro['no'].", ";
                            
                         }
                         foreach($stat as $ro1){
                            if ($ro1['status']==1) {
                                    $ro1="Active";
                            }else if($ro1['status']==0) {
                                $ro1="Pending";
                            }
                            else{
                                $ro1="Inactive";
                            }
                           $ro1= "'".$ro1."'".", ";
                             $ro1;
                         

                             //$ro1['status'].", ";
                        }

                              
                                  $conn->close();
                              ?>