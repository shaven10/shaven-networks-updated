                <?php
                        include('dbconnect.php');
                      $sql = "select count(*) as no, medium,medium_name from tbl_subs_profile as a,tbl_mediums as b where medium=b.id and status=1 group by medium;;";
                     
                        $result = $conn->query($sql);

                        $numberofSubs = array();
                        $stat = array();
                        while($row = $result->fetch_assoc()) {
                            $numberofSubs[] = $row;
                            $stat[] = $row;
                         }
                         
                         foreach($numberofSubs as $ro){
                              $ro['no'].", ";
                            
                         }
                         foreach($stat as $ro1){
                          echo "'".$ro1['medium_name']."'".", ";
                        }

                              
                                  $conn->close();
                              ?>