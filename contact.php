<?php 
$page_title = 'Contact Us';
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = htmlspecialchars(trim($_POST['name'] ?? ''));
  $email = htmlspecialchars(trim($_POST['email'] ?? ''));
  $subject = htmlspecialchars(trim($_POST['subject'] ?? ''));
  $message_text = htmlspecialchars(trim($_POST['message'] ?? ''));

  if (!empty($name) && !empty($email) && !empty($subject) && !empty($message_text)) {
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      // In a real application, you would save to database or send email
      $message = "Thank you for your message! We will get back to you soon.";
    } else {
      $error = "Please enter a valid email address.";
    }
  } else {
    $error = "Please fill in all fields.";
  }
}
?>
<?php include './include/header.php'; ?>

<main style="max-width:1200px; margin:40px auto;">
  <h1 style="text-align:left; color: #0c74a6;">Contact Us</h1>
  <p>Please write to <strong style="color:skyblue">info@archanaclinic.com.np</strong> to get corporate tie-up with us.</p>
  <p>For individual inquiries, please fill the form below:</p>

  <?php if (!empty($message)): ?>
    <div class="success-message" style="color: green; margin: 20px 0;">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="error-message" style="color: red; margin: 20px 0;">
      <?php echo $error; ?>
    </div>
  <?php endif; ?>

  <div class="contact-form">
    <h2>Send us a Message</h2>
    <p>At Archana clinic we value your feedback and your inputs. We are always willing to listen and improve!</p>

    <form method="POST" action="">
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" placeholder="Your name" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="Your email" required>

      <label for="subject">Subject</label>
      <input type="text" id="subject" name="subject" placeholder="Subject" required>

      <label for="message">Message</label>
      <textarea id="message" name="message" rows="5" placeholder="Write your message..." required></textarea>

      <button type="submit">Send Message</button>
    </form>
  </div>
</main>

<section class="contact-container">
  <?php include './include/contact-section.php'; ?>
</section>

<?php include './include/footer.php'; ?>
