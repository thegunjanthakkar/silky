<?php
exec('"C:\Program Files\Git\bin\git.exe" checkout order-confirmation.php 2>&1', $output, $return_var);
echo "Return: $return_var\n";
echo "Output:\n" . implode("\n", $output);
?>
