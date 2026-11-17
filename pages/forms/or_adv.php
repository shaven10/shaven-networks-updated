<?php
require_once __DIR__ . '/../../includes/guard.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if(!isset($_SESSION['usr_id']))

{

	header("location:login.php");

}

$sessionID=$_SESSION['usr_id'];

?>

<?php



    $datenow=date_create('Asia/Taipei');

   $PaymentTimestamp= date_format($datenow,"Y-m-d H:i:s");





  include('../../dbconnect.php');

   $sql11 = "SELECT id FROM tbl_payments order by id desc limit 1";
                               $result111 = $conn->query($sql11);
                               while($row1111 = $result111->fetch_assoc()) {
                                 $pid=$row1111['id'];
                             
                              }



    $sql1 = "SELECT * FROM tbl_payments as a,tbl_subs_profile as b,tbl_plans as c where plan_id=c.id and a.payment_uid=b.id and a.id='$pid'";
                               $result1 = $conn->query($sql1);
                               $dueText = '';
                               while($row1 = $result1->fetch_assoc()) {
                                 $PAmoutn=$row1['payment_amount'];
                                  $rf=$row1['rf'];
                                  $dateofpayment=$row1['payment_date'];
                                            $name=$row1['s_name'];
                                             $address=$row1['s_address'];
                                              $payment_month=$row1['payment_month'];
                                                $payment_year=$row1['payment_year'];
                                                $plan=$row1['p_name'];
                                                $dueText = shaven_due_text($row1, $row1['s_doi'] ?? '');
                              }

                            $conn->close();
                        ?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SN Payment Receipt</title>
     
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            color: #2c3e50;
            text-transform: uppercase;
        }

        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .receipt-info div {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #dee2e6;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .total-section {
            float: right;
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }

        .grand-total {
            font-size: 1.2em;
            font-weight: bold;
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 10px;
        }

        .footer {
            margin-top: 100px;
            text-align: center;
            font-size: 0.9em;
            color: #777;
        }

        @media print {
            body { border: none; box-shadow: none; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <h1>Payment Receipt</h1>
            <p><strong>SHAVEN NETWORKS</strong><br>
            Maralag, Dumingag<br>
            Zamboanga del Sur, Philippines 7028</p>
        </div>
        <div style="text-align: right;">
            <p><strong>Receipt #:</strong> <?php echo $rf ?><br>
            <strong>Date of Payment:</strong> <?php echo $dateofpayment?></p>
        </div>
    </div>

    <div class="receipt-info">
        <div>
            <strong>Billed To:</strong><br>
           <?php echo $name ?><br>
            <?php echo $address ?>
        </div>
        <div style="text-align: right;">
            <strong>Payment Method:</strong><br>
            Paid in Cash<br>
            <strong>Status:</strong> <span style="color: green;">Paid</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Plan Availed</th>
                <th style="text-align: right;">Unit Price</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Internet Subscription (<?php echo $payment_month; echo " "; echo $payment_year; ?><?php if (!empty($dueText)): ?><br>Due <?php echo htmlspecialchars($dueText, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>)</td>
                <td> <?php echo $plan ?></td>
                <td style="text-align: right;"><?php echo $PAmoutn1=$PAmoutn*.88; echo".00"; ?></td>
                <td style="text-align: right;"><?php echo $PAmoutn2=$PAmoutn*.88; echo".00";?></td>
            </tr>
            <tr>
                <td>Service Tax</td>
                <td><?php echo $plan ?></td>
                <td style="text-align: right;"><?php echo $PAmoutn11=$PAmoutn*.12; echo".00";?></td>
                <td style="text-align: right;"><?php echo $PAmoutn12=$PAmoutn*.12; echo".00";?></td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span>Subtotal</span>
            <span><?php echo $PAmoutn; echo".00"; ?></span>
        </div>
     
        <div class="total-row grand-total">
            <span>Total Amount Paid</span>
            <span><?php echo $PAmoutn; echo".00"; ?></span>
        </div>
    </div>

    <div style="clear: both;"></div>

    <div class="footer">
        <p>Thank you for your Payment!</p>
        <p>If you have any questions, contact us at abminevets@gmail.com</p>
        <button class="no-print" onclick="window.print()" style="margin-top: 20px; padding: 10px 20px; cursor: pointer;">Print Receipt</button>

       <a href="../tables/collections.php#pay-desk"> <button class="no-print" style="margin-top: 20px; padding: 10px 20px; cursor: pointer;">Close</button></a>
    </div>

</body>
</html>
