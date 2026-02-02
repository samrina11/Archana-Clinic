<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Full Details
$sql = "
    SELECT a.id AS appt_id, a.appointment_date, a.status AS appt_status, a.created_at AS booked_at, a.notes,
           d.specialization, d.qualification, u_doc.name AS doctor_name, u_doc.email AS doctor_email,
           p.name AS patient_name, p.phone AS patient_phone, p.address AS patient_address,
           b.id AS bill_id, b.amount, b.status AS bill_status, b.payment_method, b.payment_date,
           pay.transaction_uuid, pay.gateway_ref_id
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN billing b ON b.appointment_id = a.id
    LEFT JOIN payments pay ON pay.billing_id = b.id AND pay.status = 'completed'
    WHERE a.id = ? AND p.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Appointment record not found or access denied.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment #<?= $data['appt_id'] ?></title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        
        body.dashboard-page {
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }

        .view-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn-back {
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .btn-print {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .invoice-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            padding: 3rem;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid var(--text-main);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .brand h1 { margin: 0; color: var(--primary); text-transform: uppercase; font-size: 1.5rem; }
        .meta { text-align: right; }
        .meta h2 { margin: 0; font-size: 1.25rem; color: var(--text-main); }
        .meta p { margin: 0; color: var(--text-muted); font-size: 0.9rem; }

        .invoice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .group h3 {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
        }
        .group p { margin: 0.25rem 0; color: var(--text-main); font-size: 0.95rem; }
        .label { font-weight: 600; min-width: 80px; display: inline-block; }

        .status-paid { color: #10b981; font-weight: 700; border: 1px solid #10b981; padding: 2px 6px; border-radius: 4px; display: inline-block; }
        .status-unpaid { color: #ef4444; font-weight: 700; border: 1px solid #ef4444; padding: 2px 6px; border-radius: 4px; display: inline-block; }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .details-table th { text-align: left; background: #f8fafc; padding: 0.75rem; border-bottom: 1px solid var(--border); font-size: 0.875rem; }
        .details-table td { padding: 1rem 0.75rem; border-bottom: 1px solid var(--border); }
        .total-row td { border-top: 2px solid var(--text-main); font-weight: 700; font-size: 1.1rem; }

        .footer {
            margin-top: 3rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
        }

        @media print {
            body * { visibility: hidden; }
            .invoice-card, .invoice-card * { visibility: visible; }
            .invoice-card { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: none; padding: 0; }
            .action-bar, nav, aside { display: none !important; }
            .dashboard-container { display: block; }
            .dashboard-content { margin-left: 0; padding: 0; }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/nav.php'; ?>

        <div class="view-container">
            <div class="action-bar">
                <a href="manage-appointments.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Slip
                </button>
            </div>

            <div class="invoice-card">
                <div class="invoice-header">
                    <div class="brand">
                        <h1>Archana Clinic</h1>
                        <p style="margin: 0; font-size: 0.875rem; color: #64748b;">Excellence in Healthcare</p>
                    </div>
                    <div class="meta">
                        <h2>Appointment Slip</h2>
                        <p>#<?= str_pad($data['appt_id'], 6, '0', STR_PAD_LEFT) ?></p>
                        <p>Date: <?= date('M d, Y') ?></p>
                    </div>
                </div>

                <div class="invoice-grid">
                    <div class="group">
                        <h3>Patient Details</h3>
                        <p><span class="label">Name:</span> <?= htmlspecialchars($data['patient_name']) ?></p>
                        <p><span class="label">Phone:</span> <?= htmlspecialchars($data['patient_phone']) ?></p>
                        <p><span class="label">Address:</span> <?= htmlspecialchars($data['patient_address'] ?? 'N/A') ?></p>
                    </div>
                    <div class="group" style="text-align: right;">
                        <h3>Doctor Details</h3>
                        <p><strong>Dr. <?= htmlspecialchars($data['doctor_name']) ?></strong></p>
                        <p><?= htmlspecialchars($data['specialization']) ?></p>
                        <p><?= htmlspecialchars($data['qualification']) ?></p>
                    </div>
                </div>

                <div class="group">
                    <h3>Appointment Information</h3>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Schedule</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>General Consultation</td>
                                <td><?= date('l, F j, Y', strtotime($data['appointment_date'])) ?> at <?= date('h:i A', strtotime($data['appointment_date'])) ?></td>
                                <td><?= ucfirst($data['appt_status']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="group" style="margin-top: 2rem;">
                    <h3>Payment Status</h3>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Method</th>
                                <th>Transaction Ref</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#<?= $data['bill_id'] ?? '-' ?></td>
                                <td><?= ucfirst($data['payment_method'] ?? '-') ?></td>
                                <td style="font-family: monospace; font-size: 0.9rem;"><?= $data['transaction_uuid'] ?? $data['gateway_ref_id'] ?? '-' ?></td>
                                <td style="text-align: right;">Rs. <?= number_format($data['amount'], 2) ?></td>
                            </tr>
                            <tr class="total-row">
                                <td colspan="3" style="text-align: right;">Total Status: 
                                    <?php if ($data['bill_status'] == 'paid'): ?>
                                        <span class="status-paid">PAID</span>
                                    <?php else: ?>
                                        <span class="status-unpaid">UNPAID</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    Rs. <?= ($data['bill_status'] == 'paid') ? number_format($data['amount'], 2) : number_format($data['amount'], 2) . ' (Due)' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="footer">
                    <p>Please arrive 15 minutes before your scheduled time.</p>
                    <p>For cancellations, contact us at least 24 hours in advance.</p>
                    <p style="margin-top: 0.5rem; font-weight: 500;">Helpline: 01-4444444 | Email: info@archanaclinic.com</p>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>