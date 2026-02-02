<?php


// config.php (top part)
$pdo = new PDO("mysql:host=localhost;dbname=clinic;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
class Auth {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /* ======================
       LOGIN
    ====================== */
    public function login($email, $password) {
        $stmt = $this->conn->prepare(
            "SELECT id, name, email, password, role 
             FROM users 
             WHERE email = ? 
             LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['name']    = $user['name'];
                return true;
            }
        }
        return false;
    }

    /* ======================
       REGISTER USER (BASE)
    ====================== */
    public function register($name, $email, $password, $role = 'patient') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare(
            "INSERT INTO users (name, email, password, role, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("ssss", $name, $email, $hashed, $role);

        if (!$stmt->execute()) {
            return false;
        }

        return $stmt->insert_id;
    }

    /* ======================
       REGISTER PATIENT (FIXED)
    ====================== */
    public function registerPatient(
        $name,
        $email,
        $password,
        $phone,
        $date_of_birth,
        $gender,
        $address,
        $emergency_phone,
        $medical_history
    ) {
        // 1️⃣ Create user
        $user_id = $this->register($name, $email, $password, 'patient');
        if (!$user_id) {
            return false;
        }

        // 2️⃣ SAFE DEFAULTS (important for NOT NULL columns)
        $date_of_birth     = $date_of_birth ?: 'unknown';
        $gender            = $gender ?: 'other';
        $address           = $address ?: 'Not provided';
        $emergency_phone   = $emergency_phone ?: 'Not provided';
        $medical_history   = $medical_history ?: '';

        // 3️⃣ Insert patient profile
        $stmt = $this->conn->prepare("
            INSERT INTO patients
            (user_id, phone, date_of_birth, gender, address, emergency_phone, medical_history)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "issssss",
            $user_id,
            $phone,
            $date_of_birth,
            $gender,
            $address,
            $emergency_phone,
            $medical_history
        );

        return $stmt->execute();
    }

    /* ======================
       HELPERS
    ====================== */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function logout() {
        session_unset();
        session_destroy();
    }

    public function getUser() {
        if (!$this->isLoggedIn()) return null;

        $user_id = $_SESSION['user_id'];

        $stmt = $this->conn->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.role,
                p.phone,
                p.gender,
                p.address,
                p.medical_history,
                p.blood_group,
                p.date_of_birth,
                p.allergies,
                p.emergency_phone
                    
            FROM users u
            LEFT JOIN patients p ON u.id = p.user_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

/* ======================
   INIT AUTH OBJECT
====================== */
if (!isset($auth)) {
    $auth = new Auth($conn);
}


function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>