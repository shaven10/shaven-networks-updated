                <?php
                        include('dbconnect.php');
                                    //date of today
                                        $datenow=date_create('Asia/Taipei');
                                        $DateofToday= date_format($datenow,"Y-m-d");
                                      // $DateofToday= '2023-04-10';
                                        $DofToday= date_format($datenow,"d");
                                        $MonthofToday= date_format($datenow,"F");
                                        //end
                   //     $sql = "select sum(payment_amount) as summary,concat(payment_month,', ',payment_year) as due from tbl_payments where status<>0 group by payment_month,payment_year";
                        $sql = "select payment_date,payment_amount,payment_uid,LEFT(s_name,5) as s_name,payment_month as due from tbl_payments as a,tbl_months as b,tbl_subs_profile as c where b.month_name=payment_month and payment_uid=c.id and a.status=1 and DATE(payment_date)='".$DateofToday."' order by payment_date asc;";
                     
                        $result = $conn->query($sql);

                        $summMonth = array();
                        $summAmount = array();
                        while($row = $result->fetch_assoc()) {
                            $summMonth[] = $row;
                            $summAmount[] = $row;
                         }
                         
                         foreach($summMonth as $ro){
                             echo "'".$ro['s_name']."'".", ";
                         }
                         foreach($summAmount as $ro1){
                            $ro1['payment_amount'].", ";
                        }
                     //   echo "'January', 'February', 'March',";




                    /*    while($row = $result->fetch_assoc()) {
                         $summary=$row['due'];
                       echo  $summary=$summary.' ,';
                          $due=$row['due'];
                         
                              } */
                              
                                  $conn->close();
                              ?>