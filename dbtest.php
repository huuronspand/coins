<?php
$servername = "104.199.87.144";
$username = "root";
$password = "xiObe3665dksyiLD";

// Create connection
$conn = new mysqli($servername, $username, $password );

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";

$sql = "INSERT INTO coins.dbtest (timestamp,txt) values (unix_timestamp(),'dbtest')";
if ($conn->query($sql) === TRUE) {
    echo "New record created successfully";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>