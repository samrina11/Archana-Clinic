
<?php
/*
$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "clinic";
$port       = 3306;

/* =========================
   CONNECT TO MYSQL SERVER
========================= */

/*
$conn = new mysqli($servername, $username, $password, "", $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =========================
   CREATE DATABASE
========================= */

/*
$sql = "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$conn->query($sql)) {
    die("Error creating database: " . $conn->error);
}

echo "✓ Database created/exists successfully<br>";

$conn->select_db($database);
$conn->set_charset("utf8mb4");

/* =========================
   USERS TABLE
========================= */

/*
$users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','doctor','patient') NOT NULL,
    phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($users) or die($conn->error);
echo "✓ Users table ready<br>";

/* =========================
   PATIENTS TABLE
========================= */

/*
$patients = "CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    age INT,
    gender ENUM('Male','Female','Other'),
    blood_group VARCHAR(5),
    address TEXT,
    medical_history TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($patients) or die($conn->error);
echo "✓ Patients table ready<br>";

/* =========================
   DOCTORS TABLE
========================= */

/*
$doctors = "CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(100),
    qualification VARCHAR(100),
    experience_years INT,
    consultation_fee DECIMAL(10,2),
    available_days VARCHAR(200),
    available_time_start TIME,
    available_time_end TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($doctors) or die($conn->error);
echo "✓ Doctors table ready<br>";

/* =========================
   APPOINTMENTS TABLE
========================= */

/*
$appointments = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
)";
$conn->query($appointments) or die($conn->error);
echo "✓ Appointments table ready<br>";

/* =========================
   BILLING TABLE
========================= */


/*
$billing = "CREATE TABLE IF NOT EXISTS billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
    payment_method VARCHAR(50),
    payment_date DATETIME,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
)";
$conn->query($billing) or die($conn->error);
echo "✓ Billing table ready<br>";

/* =========================
   TEST DATA
========================= */

/*
$conn->query("
INSERT IGNORE INTO users (id,name,email,password,role,phone) VALUES
(1,'Admin','admin@archana.com','" . password_hash('admin123', PASSWORD_DEFAULT) . "','admin','9999999999'),
(2,'Dr Priya','dpriya@archana.com','" . password_hash('doctor@123', PASSWORD_DEFAULT) . "','doctor','9826543215'),
(3,'Ram Shrestha','ram12@gmail.com','" . password_hash('ram@123', PASSWORD_DEFAULT) . "','patient','9823605820')
");

$conn->query("
INSERT IGNORE INTO doctors (user_id,specialization,qualification,experience_years,consultation_fee,available_days,available_time_start,available_time_end)
VALUES (2,'Pediatrician','MBBS, MD',6,400,'Mon,Tue,Thu,Fri','10:00','17:00')
");

$conn->query("
INSERT IGNORE INTO patients (user_id,age,gender,blood_group,address)
VALUES (3,27,'Male','O-','Balkot, Bhaktapur')
");

echo "<br>✓ Test data inserted<br>";
echo "<br><b>DONE ✅ Database initialized successfully</b>";

$conn->close();
