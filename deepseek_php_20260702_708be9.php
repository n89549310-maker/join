<?php
// ---------- Database Connection ----------
$servername = "localhost";
$username = "root";      // apna username
$password = "";          // apna password
$dbname = "agent_onboarding";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- Salary Calculation Function ----------
function calculateSalary($level, $performance) {
    // Base daily pay (basic + WhatsApp subsidy)
    $basic = 0;
    if ($level == 'S2') {
        $basic = 400; // 300 + 100
    } elseif ($level == 'S1' || $level == 'T0') {
        $basic = 600; // 500 + 100
    } else {
        return ['total' => 0, 'breakup' => 'Invalid Level'];
    }

    $bonus = 0;
    $penalty = 0;
    $total = $basic;

    if ($level == 'S2') {
        // S2: 500 per case
        $bonus = 500 * intval($performance);
        $total += $bonus;
        $breakup = "Basic + Subsidy = $basic, Bonus ($performance cases × 500) = $bonus";
    } elseif ($level == 'S1') {
        $cases = intval($performance);
        if ($cases >= 3) {
            // Bonus slabs based on cases (approximated from given table)
            if ($cases >= 3 && $cases <= 5) {
                $bonus = 800;
            } elseif ($cases >= 6 && $cases <= 8) {
                $bonus = 2400;
            } elseif ($cases > 8) {
                $bonus = 4000; // assumption for high cases
            } else {
                $bonus = 0;
            }
            $total += $bonus;
            $breakup = "Basic + Subsidy = $basic, Bonus (meets target) = $bonus";
        } else {
            // Missed target -> penalty -500
            $penalty = -500;
            $total += $penalty;
            $breakup = "Basic + Subsidy = $basic, Penalty for missing 3 cases = -500";
        }
    } elseif ($level == 'T0') {
        $rate = floatval($performance);
        if ($rate >= 28) {
            // Bonus based on rate slabs (approx from table)
            if ($rate >= 50) {
                $bonus = 3000;
            } elseif ($rate >= 34) {
                $bonus = 1200;
            } elseif ($rate >= 28) {
                $bonus = 200; // just meeting target
            } else {
                $bonus = 0;
            }
            $total += $bonus;
            $breakup = "Basic + Subsidy = $basic, Bonus (rate $rate%) = $bonus";
        } else {
            $penalty = -500;
            $total += $penalty;
            $breakup = "Basic + Subsidy = $basic, Penalty for rate < 28% = -500";
        }
    }

    return ['total' => $total, 'breakup' => $breakup];
}

// ---------- Form Submission Handling ----------
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $full_name = $_POST['full_name'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];
    $aadhaar_number = $_POST['aadhaar_number'];
    $address = $_POST['address'];
    $bank_name = $_POST['bank_name'];
    $account_number = $_POST['account_number'];
    $ifsc_code = $_POST['ifsc_code'];
    $upi_id = $_POST['upi_id'];
    $experience = $_POST['experience'];
    $level = $_POST['level'];
    $performance = $_POST['performance'];

    // Handle file uploads (store in 'uploads/' folder)
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $aadhaar_front = "";
    $aadhaar_back = "";
    $selfie = "";

    if ($_FILES["aadhaar_front"]["name"]) {
        $aadhaar_front = $target_dir . time() . "_front_" . basename($_FILES["aadhaar_front"]["name"]);
        move_uploaded_file($_FILES["aadhaar_front"]["tmp_name"], $aadhaar_front);
    }
    if ($_FILES["aadhaar_back"]["name"]) {
        $aadhaar_back = $target_dir . time() . "_back_" . basename($_FILES["aadhaar_back"]["name"]);
        move_uploaded_file($_FILES["aadhaar_back"]["tmp_name"], $aadhaar_back);
    }
    if ($_FILES["selfie"]["name"]) {
        $selfie = $target_dir . time() . "_selfie_" . basename($_FILES["selfie"]["name"]);
        move_uploaded_file($_FILES["selfie"]["tmp_name"], $selfie);
    }

    // Calculate salary
    $salaryResult = calculateSalary($level, $performance);
    $daily_salary = $salaryResult['total'];

    // Insert into database
    $sql = "INSERT INTO agents 
            (full_name, gender, age, aadhaar_number, address, aadhaar_front, aadhaar_back, selfie, 
             bank_name, account_number, ifsc_code, upi_id, experience, level, performance_metric, daily_salary) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissssssssssssd", 
        $full_name, $gender, $age, $aadhaar_number, $address, 
        $aadhaar_front, $aadhaar_back, $selfie, 
        $bank_name, $account_number, $ifsc_code, $upi_id, 
        $experience, $level, $performance, $daily_salary
    );

    if ($stmt->execute()) {
        $message = "<div style='color:green;'>✅ Agent successfully onboarded! Daily Salary: ₹" . number_format($daily_salary, 2) . "<br>Breakup: " . $salaryResult['breakup'] . "</div>";
    } else {
        $message = "<div style='color:red;'>❌ Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Onboarding Form</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f4f8; margin: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; }
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 200px; }
        .salary-section { background: #eef6ff; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #007bff; }
        .salary-section h3 { margin-top: 0; }
        button { background: #007bff; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #0056b3; }
        .msg { margin: 20px 0; padding: 10px; background: #f8f9fa; border-radius: 6px; }
        .file-upload { padding: 5px; }
        .info-note { background: #fff3cd; padding: 10px; border-radius: 6px; margin: 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <h2>📋 Agent Onboarding & Salary Calculator</h2>

    <?php if ($message) echo "<div class='msg'>$message</div>"; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <!-- Personal Details -->
        <div class="row">
            <div class="col">
                <div class="form-group">
                    <label>Full Name (as per Aadhaar)</label>
                    <input type="text" name="full_name" required>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label>Age</label>
                    <input type="number" name="age" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Aadhaar Number</label>
            <input type="text" name="aadhaar_number" placeholder="XXXX XXXX XXXX" required>
        </div>

        <div class="form-group">
            <label>Home Address</label>
            <textarea name="address" rows="2" required></textarea>
        </div>

        <!-- File Uploads -->
        <div class="row">
            <div class="col">
                <div class="form-group">
                    <label>Aadhaar Front Photo</label>
                    <input type="file" name="aadhaar_front" accept="image/*" required>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label>Aadhaar Back Photo</label>
                    <input type="file" name="aadhaar_back" accept="image/*" required>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label>Selfie (for verification)</label>
                    <input type="file" name="selfie" accept="image/*" required>
                </div>
            </div>
        </div>

        <!-- Bank Details -->
        <div class="row">
            <div class="col">
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" required>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" required>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc_code" required>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>UPI ID (optional)</label>
            <input type="text" name="upi_id">
        </div>

        <div class="form-group">
            <label>Experience</label>
            <select name="experience" required>
                <option value="Fresher">Fresher</option>
                <option value="Experienced">Experienced</option>
            </select>
        </div>

        <!-- ========== SALARY CALCULATOR SECTION ========== -->
        <div class="salary-section">
            <h3>💰 Daily Salary Calculator</h3>
            <p class="info-note">📌 <strong>Salary Structure:</strong><br>
                S2: ₹400/day (Basic ₹300 + WhatsApp ₹100) + ₹500 per case.<br>
                S1 & T0: ₹600/day (Basic ₹500 + WhatsApp ₹100). Bonus if target met: S1 = 3 cases, T0 = 28% rate. Penalty -500 if miss.
            </p>
            <div class="row">
                <div class="col">
                    <div class="form-group">
                        <label>Your Level</label>
                        <select name="level" id="level" required>
                            <option value="S2">S2</option>
                            <option value="S1">S1</option>
                            <option value="T0">T0</option>
                        </select>
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label>Performance (cases for S1/S2, conversion % for T0)</label>
                        <input type="text" name="performance" id="performance" placeholder="e.g., 4 or 34.6" required>
                    </div>
                </div>
            </div>
            <div style="margin-top:10px; font-weight:bold; background:#d4edda; padding:10px; border-radius:6px;">
                💵 Your Calculated Daily Pay: <span id="liveSalary">₹0.00</span>
            </div>
            <p style="font-size:0.9rem; color:#555;">(आप level और performance डालते ही ये auto-calculate हो जाएगा)</p>
        </div>

        <button type="submit">🚀 Submit & Onboard Agent</button>
    </form>
</div>

<!-- JavaScript for Live Salary Calculation -->
<script>
    function updateSalary() {
        const level = document.getElementById('level').value;
        const perf = document.getElementById('performance').value;
        if (!perf) {
            document.getElementById('liveSalary').innerText = '₹0.00';
            return;
        }
        // Send AJAX request to a separate PHP file or do client-side calculation.
        // To keep it simple, we'll call a PHP endpoint via fetch.
        // But we can also calculate client-side using same logic as PHP.
        // Let's implement client-side logic (mirroring PHP calculateSalary)
        let total = 0;
        let basic = 0;
        if (level === 'S2') basic = 400;
        else if (level === 'S1' || level === 'T0') basic = 600;
        else { document.getElementById('liveSalary').innerText = '₹0.00'; return; }

        let bonus = 0;
        let penalty = 0;
        total = basic;

        if (level === 'S2') {
            const cases = parseInt(perf);
            if (!isNaN(cases)) {
                bonus = 500 * cases;
                total += bonus;
            }
        } else if (level === 'S1') {
            const cases = parseInt(perf);
            if (!isNaN(cases)) {
                if (cases >= 3) {
                    if (cases >= 3 && cases <= 5) bonus = 800;
                    else if (cases >= 6 && cases <= 8) bonus = 2400;
                    else if (cases > 8) bonus = 4000;
                    total += bonus;
                } else {
                    penalty = -500;
                    total += penalty;
                }
            }
        } else if (level === 'T0') {
            const rate = parseFloat(perf);
            if (!isNaN(rate)) {
                if (rate >= 28) {
                    if (rate >= 50) bonus = 3000;
                    else if (rate >= 34) bonus = 1200;
                    else bonus = 200;
                    total += bonus;
                } else {
                    penalty = -500;
                    total += penalty;
                }
            }
        }
        document.getElementById('liveSalary').innerText = '₹' + total.toFixed(2);
    }

    document.getElementById('level').addEventListener('change', updateSalary);
    document.getElementById('performance').addEventListener('input', updateSalary);
    // initial call
    window.onload = function() {
        updateSalary();
    };
</script>

</body>
</html>