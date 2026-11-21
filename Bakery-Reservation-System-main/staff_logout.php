<?php
// ni logout staff kalau dah masuk dlm stf
session_start();
session_unset();
session_destroy();
header("Location: index.html");
exit();
?>