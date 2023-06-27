<?php
                      include('../../dbconnect.php');

                       //date of today
                    $datenow=date_create('Asia/Taipei');
                    $DateofToday= date_format($datenow,"m-d-Y");
                    $DateofToday1= date_format($datenow,"Y-m-d");
                    $DofToday= date_format($datenow,"d");
                    $YearofToday= date_format($datenow,"Y");
                    $MonthofToday= date_format($datenow,"F");
                    //end


                



                    $sql = "SELECT p_speed_kb,s_name,p_name,s_doi,p_monthly_amount,a.id as id FROM tbl_subs_profile as a,tbl_plans as b where a.plan_id=b.id and a.status='1'";
                    $result = $conn->query($sql);
                    if ($result->num_rows > 0) {
                      // output data of each row
                      while($row = $result->fetch_assoc()) {
                        $date=date_create($row['s_doi']);
                         $dofinstallation=date_format($date,"Y-m-d");
                        $pid=$row['id'];

                        //Calculate the date difference based on timestamp values
                        $difference = strtotime($dofinstallation) - strtotime($DateofToday1);

                        //Calculate difference in days
                      $days = (int)abs($difference/(60 * 60)/24);
                     // echo "<br/>The difference between ".$DateofToday1." and ".$dofinstallation." is ".$days." days.";
                     // <td>".$row['id']."</td>
                      

                      echo "<tr>
                 
                     
                      <td>".date_format($date,"F d, Y")."</td>
                        <td>".$row['s_name']."</td>
                        <td>".$row['p_name'].' ('.$row['p_speed_kb']. 'kb)'."</td>
                        <td>".$row['p_monthly_amount']."</td> </tr>";
                              $payment_day=date_format($date,"d");
                              $p_amount=$row['p_monthly_amount'];
                            
                              if ($days>30){

                              if ($DofToday>=$payment_day){   

                                        $sql1 = "SELECT * FROM tbl_payments where payment_year='$YearofToday' and payment_month='$MonthofToday' and payment_uid='$pid'";
                                        $result1 = $conn->query($sql1);
                                        //echo $result1->num_rows;
                                        if ($result1->num_rows > 0) {
                                         
                                          
                                        }else{
                                            //add to colektahonon table
                                            $sql = "INSERT INTO tbl_payments (payment_year,payment_amount,payment_day,payment_month,payment_uid,p_timestamp)
                                            VALUES ('$YearofToday','$p_amount','$payment_day','$MonthofToday','$pid',now());";
                                            if ($conn->multi_query($sql) === TRUE) {
                                              // echo '<script> alert("New records created successfully");</script>'; 
                                              } else {
                                                  echo "Error: " . $sql . "<br>" . $conn->error;
                                                  }
                                            // 
                                        }
                                          
                                
                                            
                              }else{

                                //do nothing
                              }
                            }
                             

                     
                      }
                    } else {
                      echo "0 results";
                    }
                  
                    $conn->close();
                     
                  ?>