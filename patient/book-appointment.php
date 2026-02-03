<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

// Auth check
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Patient
$stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient profile not found.");
}

// Doctors
$doctors = $conn->query("
    SELECT d.id, u.name AS doctor_name, d.specialization,
           d.consultation_fee
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.specialization
");

$error = '';
$success = '';
$show_payment = false;
$payment_info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $raw_datetime = trim($_POST['appointment_date'] ?? '');

    // 🔴 CRITICAL FIX: convert datetime-local → MySQL DATETIME
    $appointment_date = date('Y-m-d H:i:s', strtotime($raw_datetime));

    if (!$doctor_id || !$raw_datetime || !$reason) {
        $error = "All fields are required.";
    } elseif (strlen($reason) < 10) {
        $error = "Reason must be at least 10 characters.";
    } elseif (strtotime($appointment_date) <= strtotime('+1 day')) {
        $error = "Appointment must be at least 24 hours in advance.";
    } else {

        // Doctor fee
        $fee_stmt = $conn->prepare(
            "SELECT consultation_fee FROM doctors WHERE id = ?"
        );
        $fee_stmt->bind_param("i", $doctor_id);
        $fee_stmt->execute();
        $consultation_fee = $fee_stmt->get_result()
            ->fetch_assoc()['consultation_fee'] ?? 500;

        // Availability check
        $check_stmt = $conn->prepare("
            SELECT id FROM appointments
            WHERE doctor_id = ?
              AND appointment_date = ?
              AND status != 'cancelled'
        ");
        $check_stmt->bind_param("is", $doctor_id, $appointment_date);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "This time slot is already booked.";
        } else {

            // Insert appointment
            $insert_stmt = $conn->prepare("
                INSERT INTO appointments
                (patient_id, doctor_id, appointment_date, notes, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $insert_stmt->bind_param(
                "iiss",
                $patient['id'],
                $doctor_id,
                $appointment_date,
                $reason
            );

            if ($insert_stmt->execute()) {

                $appointment_id = $conn->insert_id;
                $due_date = date('Y-m-d', strtotime($appointment_date));

                // Billing
                $bill_stmt = $conn->prepare("
                    INSERT INTO billing
                    (patient_id, appointment_id, amount, status, due_date, created_at)
                    VALUES (?, ?, ?, 'unpaid', ?, NOW())
                ");
                $bill_stmt->bind_param(
                    "iids",
                    $patient['id'],
                    $appointment_id,
                    $consultation_fee,
                    $due_date
                );

                if ($bill_stmt->execute()) {
                    $show_payment = true;
                    $payment_info = [
                        'billing_id' => $conn->insert_id,
                        'amount' => $consultation_fee,
                        'appt_id' => $appointment_id,
                        'date' => $appointment_date
                    ];
                    $success = "Appointment booked successfully.";
                } else {
                    $error = "Billing creation failed.";
                }

            } else {
                $error = "Appointment booking failed.";
            }
        }
    }
}

// Date limits
$min_date = date('Y-m-d\TH:i', strtotime('+1 day'));
$max_date = date('Y-m-d\TH:i', strtotime('+3 months'));
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb; /* Enterprise Blue */
            --bg-body: #f8fafc; /* Slate 50 */
            --bg-card: #ffffff;
            --text-main: #0f172a; /* Slate 900 */
            --text-muted: #64748b; /* Slate 500 */
            --border: #e2e8f0; /* Slate 200 */
            --input-bg: #f1f5f9;
            --esewa: #60bb46;
            --khalti: #5C2D91;
        }

        body.dashboard-page {
            background-color: var(--bg-body);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
        }

        .booking-container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 0 1rem;
        }

        /* Progress Indicator */
        .progress-header {
            display: flex;
            justify-content: center;
            margin-bottom: 2.5rem;
        }
        .progress-steps {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 0.75rem 1.5rem;
            border-radius: 9999px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .step-item.active {
            color: var(--primary);
        }
        .step-circle {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--input-bg);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        .step-item.active .step-circle {
            background: var(--primary);
            color: white;
        }
        .step-separator {
            width: 20px;
            height: 2px;
            background: var(--border);
        }

        /* Main Card */
        .main-card {
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .card-header {
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .card-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .card-header p {
            margin: 0.5rem 0 0;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Elements */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .col-span-2 { grid-column: span 2; }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: #fff;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .fee-display {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: var(--primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-footer {
            margin-top: 2rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
        }
        .btn-primary { background: var(--text-main); color: white; }
        .btn-primary:hover { background: #000; transform: translateY(-1px); }
        
        .btn-secondary { background: white; border: 1px solid var(--border); color: var(--text-muted); }
        .btn-secondary:hover { background: var(--bg-body); color: var(--text-main); }

        /* Payment Section (Success State) */
        .success-state {
            text-align: center;
            padding: 1rem 0;
        }
        .success-icon {
            width: 64px;
            height: 64px;
            background: #ecfdf5;
            color: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
        }
        .receipt-box {
            background: var(--bg-body);
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .receipt-row.total {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--border);
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-main);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .pay-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: opacity 0.2s;
        }
        .pay-btn:hover { opacity: 0.9; }
        .bg-esewa { background: var(--esewa); }
        .bg-khalti { background: var(--khalti); }
        .bg-cash { background: var(--text-main); }

        /* Mobile */
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .col-span-2 { grid-column: span 1; }
            .payment-methods { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="dashboard-container">
        <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

        <main class="dashboard-content">
            <?php include __DIR__ . '/../include/nav.php'; ?>

            <div class="booking-container">
                
                <!-- Progress Indicator -->
                <div class="progress-header">
                    <div class="progress-steps">
                        <div class="step-item <?= !$show_payment ? 'active' : '' ?>">
                            <div class="step-circle">1</div>
                            <span>Details</span>
                        </div>
                        <div class="step-separator"></div>
                        <div class="step-item <?= $show_payment ? 'active' : '' ?>">
                            <div class="step-circle">2</div>
                            <span>Payment</span>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="main-card">
                    <?php if (!$show_payment): ?>
                        <!-- STEP 1: APPOINTMENT DETAILS -->
                        <div class="card-header">
                            <h1>Schedule Appointment</h1>
                            <p>Fill in the details below to book your consultation.</p>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="bookingForm">
                                <div class="form-grid">
                                    <div class="form-group col-span-2">
                                        <label for="doctor_id">Select Specialist</label>
                                        <select id="doctor_id" name="doctor_id" class="form-select" required onchange="updateFee(this)">
                                            <option value="" data-fee="0">Choose a doctor...</option>
                                            <?php 
                                            $doctors->data_seek(0);
                                            while ($doc = $doctors->fetch_assoc()): 
                                            ?>
                                                <option value="<?= $doc['id'] ?>" 
                                                        data-fee="<?= $doc['consultation_fee'] ?>"
                                                        <?= (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doc['id']) ? 'selected' : ''; ?>>
                                                    Dr. <?= htmlspecialchars($doc['doctor_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="appointment_date">Preferred Date & Time</label>
                                        <input type="datetime-local" 
                                               id="appointment_date" 
                                               name="appointment_date" 
                                               class="form-input"
                                               min="<?= $min_date ?>"
                                               max="<?= $max_date ?>"
                                               value="<?= isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date']) : '' ?>"
                                               required>
                                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Must be 24h in advance.</p>
                                    </div>

                                    <div class="form-group">
                                        <label>Consultation Fee</label>
                                        <div class="fee-display">
                                            <span>Total Due</span>
                                            <span id="fee_text">Rs. 0.00</span>
                                        </div>
                                    </div>

                                    <div class="form-group col-span-2">
                                        <label for="reason">Reason for Visit</label>
                                        <textarea id="reason" name="reason" class="form-textarea" placeholder="Please describe your symptoms..." required minlength="10"><?= isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : '' ?></textarea>
                                        <div style="display: flex; justify-content: flex-end; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                            <span id="charCount">0</span>/500
                                        </div>
                                    </div>
                                </div>

                                <div class="form-footer">
                                    <a href="../../dashboards/patient-dashboard.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        Continue to Payment <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- STEP 2: CHECKOUT / PAYMENT -->
                        <div class="card-body success-state">
                            <div class="success-icon"><i class="fas fa-check"></i></div>
                            <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0;">Booking Successful!</h2>
                            <p style="color: var(--text-muted);">Your appointment has been tentatively reserved.</p>

                            <div class="receipt-box">
                                <div class="receipt-row">
                                    <span>Booking Reference</span>
                                    <span style="font-family: monospace;">#<?= str_pad($payment_info['appt_id'], 6, '0', STR_PAD_LEFT) ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span>Date</span>
                                    <span><?= date('M d, Y - h:i A', strtotime($payment_info['date'])) ?></span>
                                </div>
                                <div class="receipt-row total">
                                    <span>Total Payable</span>
                                    <span>Rs. <?= number_format($payment_info['amount'], 2) ?></span>
                                </div>
                            </div>

                            <p style="font-size: 0.875rem; color: var(--text-main); font-weight: 600; margin: 1rem; text-align: left;">Select Payment Method:</p>

                            <div class="payment-methods" style="margin: 1rem;">
                                <form action="pay-bills.php" method="POST">
                                    <input type="hidden" name="action" value="initiate_esewa">
                                    <input type="hidden" name="billing_id" value="<?= $payment_info['billing_id'] ?>">
                                    <input type="hidden" name="amount" value="<?= $payment_info['amount'] ?>">
                                    <button type="submit" class="pay-btn bg-esewa">
                                        <img src="https://esewa.com.np/common/images/esewa_logo.png" alt="" style="height: 18px; filter: brightness(0) invert(1);"> Pay with eSewa
                                    </button>
                                </form>

                                <form action="pay-bills.php" method="POST">
                                    <input type="hidden" name="action" value="initiate_khalti">
                                    <input type="hidden" name="billing_id" value="<?= $payment_info['billing_id'] ?>">
                                    <input type="hidden" name="amount" value="<?= $payment_info['amount'] ?>">
                                    <button type="submit" class="pay-btn bg-khalti">
                                        <i class="fas fa-wallet"></i> Pay with Khalti
                                    </button>
                                </form>

                                <form action="pay-bills.php" method="POST">
                                    <input type="hidden" name="action" value="confirm_cash">
                                    <input type="hidden" name="billing_id" value="<?= $payment_info['billing_id'] ?>">
                                    <input type="hidden" name="amount" value="<?= $payment_info['amount'] ?>">
                                    <button type="submit" class="pay-btn bg-cash">
                                        <i class="fas fa-money-bill-wave"></i> Pay Cash Later
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updateFee(select) {
            const option = select.options[select.selectedIndex];
            const fee = option.getAttribute('data-fee');
            const display = document.getElementById('fee_text');
            if (fee) {
                display.innerText = 'Rs. ' + parseFloat(fee).toFixed(2);
            } else {
                display.innerText = 'Rs. 0.00';
            }
        }

        window.addEventListener('load', function() {
            const docSelect = document.getElementById('doctor_id');
            if(docSelect && docSelect.value) {
                updateFee(docSelect);
            }
        });

        const reasonInput = document.getElementById('reason');
        if(reasonInput) {
            reasonInput.addEventListener('input', function() {
                const len = this.value.length;
                document.getElementById('charCount').textContent = len;
            });
        }

        const dateInput = document.getElementById('appointment_date');
        if(dateInput) {
            dateInput.addEventListener('change', function() {
                const selected = new Date(this.value);
                const min = new Date();
                min.setDate(min.getDate() + 1);
                if(selected < min) {
                    alert("Please select a date at least 24 hours from now.");
                    this.value = '';
                }
            });
        }
    </script>
</body>
</html>