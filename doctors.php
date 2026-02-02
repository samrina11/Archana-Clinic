<?php $page_title = 'Our Doctors'; ?>
<?php include './include/header.php'; ?>

<main style="max-width:1200px; margin:40px auto;">
  <div class="container">
    <div class="header-container">
      <h4 style="color:#0c74a6; font-size:20px; padding: 25px;">MEET OUR TEAM</h4>
      <h2 style="font-size: 30px; font-weight: 600; color:#0c74a6; margin-top: 5px;">Specialist Doctors</h2>
    </div>

    <p class="description" style="font-weight: bolder; text-align: center;"> 
      Our doctors' team consists of highly skilled and experienced physicians who are dedicated to providing the best possible care for our patients. With a wide range of qualifications and specializations, our doctors work together seamlessly to deliver comprehensive, compassionate, and state-of-the-art medical treatment.
    </p>

    <?php include './include/doctors-list.php'; ?>
  </div>
</main>

<section class="contact-container">
  <?php include './include/contact-section.php'; ?>
</section>

<?php include './include/footer.php'; ?>
