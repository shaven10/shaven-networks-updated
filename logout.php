<?php
session_start();
if(isset($_SESSION['usr_id'])) {
	session_destroy();
	header("Location: login.php");
	echo "<script type='text/javascript'>alert('You have successfully logged out!!!')</script>";
} else {
	header("Location: index.php");
	echo "<script type='text/javascript'>alert('You have successfully logged out!!!')</script>";
}
?>